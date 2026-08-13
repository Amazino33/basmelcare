<?php

namespace App\Imports;

use App\Models\Batch;
use App\Models\Product;
use App\Models\StockMovement;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ProductsImport
{
    public array $created    = [];
    public array $batchAdded = [];
    public array $errors     = [];

    public function import(string $filePath): void
    {
        $spreadsheet = IOFactory::load($filePath);
        $rows        = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);

        if (empty($rows)) {
            $this->errors[] = 'File is empty.';
            return;
        }

        // Row 0 is the header — skip it
        foreach (array_slice($rows, 1) as $index => $row) {
            $rowNum = $index + 2;
            try {
                $this->processRow($row, $rowNum);
            } catch (\Throwable $e) {
                $this->errors[] = "Row {$rowNum}: " . $e->getMessage();
            }
        }
    }

    private function processRow(array $row, int $rowNum): void
    {
        $name      = trim((string) ($row[0] ?? ''));
        $batchNum  = trim((string) ($row[1] ?? ''));
        $expiryRaw = $row[2] ?? '';
        $qty       = (int) ($row[3] ?? 0);
        $costPrice = (float) ($row[4] ?? 0);
        $sellPrice = (float) ($row[5] ?? 0);

        if ($name === '') return; // blank row — skip silently

        if ($qty <= 0) {
            $this->errors[] = "Row {$rowNum} ({$name}): Qty must be greater than 0.";
            return;
        }
        if ($costPrice <= 0) {
            $this->errors[] = "Row {$rowNum} ({$name}): Cost price must be greater than 0.";
            return;
        }

        $expiry = $this->parseExpiry($expiryRaw);
        if (!$expiry) {
            $this->errors[] = "Row {$rowNum} ({$name}): Invalid expiry date '{$expiryRaw}'. Use MM/YYYY (e.g. 08/2026).";
            return;
        }

        if ($sellPrice <= 0) {
            $sellPrice = (float) (ceil(($costPrice * 1.4) / 100) * 100);
        }

        $batchNum = $batchNum ?: 'IMP-' . now()->format('Ymd') . '-' . $rowNum;

        $product = Product::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();

        if ($product) {
            $batch = Batch::create([
                'product_id'   => $product->id,
                'batch_number' => $batchNum,
                'expiry_date'  => $expiry,
                'cost_price'   => $costPrice,
                'quantity'     => $qty,
            ]);
            StockMovement::create([
                'batch_id'  => $batch->id,
                'quantity'  => $qty,
                'type'      => 'purchase',
                'reference' => 'Excel import',
            ]);
            $this->batchAdded[] = $name;
        } else {
            $product = Product::create([
                'name'          => $name,
                'category_id'   => $this->detectCategoryId($name),
                'selling_price' => $sellPrice,
                'reorder_level' => 0,
            ]);
            $batch = Batch::create([
                'product_id'   => $product->id,
                'batch_number' => $batchNum,
                'expiry_date'  => $expiry,
                'cost_price'   => $costPrice,
                'quantity'     => $qty,
            ]);
            StockMovement::create([
                'batch_id'  => $batch->id,
                'quantity'  => $qty,
                'type'      => 'purchase',
                'reference' => 'Excel import',
            ]);
            $this->created[] = $name;
        }
    }

    private function parseExpiry(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;

        // Excel serial number (date cell not formatted as text)
        if (is_numeric($value) && (float) $value > 1000) {
            try {
                $dt = ExcelDate::excelToDateTimeObject((float) $value);
                return Carbon::instance($dt)->endOfMonth()->toDateString();
            } catch (\Throwable) {}
        }

        $value = trim((string) $value);

        // MM/YYYY  e.g. 08/2026
        if (preg_match('/^(\d{1,2})\/(\d{4})$/', $value, $m)) {
            return Carbon::createFromDate((int) $m[2], (int) $m[1], 1)->endOfMonth()->toDateString();
        }

        // MM/YY  e.g. 8/27  (year as 20xx)
        if (preg_match('/^(\d{1,2})\/(\d{2})$/', $value, $m)) {
            return Carbon::createFromDate(2000 + (int) $m[2], (int) $m[1], 1)->endOfMonth()->toDateString();
        }

        // YYYY-MM  e.g. 2026-08
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $value, $m)) {
            return Carbon::createFromDate((int) $m[1], (int) $m[2], 1)->endOfMonth()->toDateString();
        }

        try {
            return Carbon::parse($value)->endOfMonth()->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function detectCategoryId(string $productName): int
    {
        $n = strtolower($productName);

        // Paediatric antibiotics — liquid antibiotic forms
        $isLiquid     = str_contains($n, 'syrup') || str_contains($n, 'suspension') || str_contains($n, 'drop');
        $isAntibiotic = str_contains($n, 'amox') || str_contains($n, 'ampic') || str_contains($n, 'aquaclav')
            || str_contains($n, 'augment') || str_contains($n, 'erythro') || str_contains($n, 'azithro')
            || str_contains($n, 'cefurox') || str_contains($n, 'nonispa') || str_contains($n, 'cefixime');
        if ($isLiquid && $isAntibiotic) return 16;

        $rules = [
            19 => ['amlodipine', 'lisinopril', 'atenolol', 'losartan', 'ramipril', 'nifedipine', 'valsartan', 'captopril', 'furosemide', 'spironolactone', 'pocco'],
            15 => ['ampiclox', 'amoxil', 'amoxicillin', 'ampicillin', 'augmentin', 'aquaclav', 'tetracycline', 'erythromax', 'erythromycin', 'azithromycin', 'ciprofloxacin', 'levofloxacin', 'cefuroxime', 'cefixime', 'cloxacillin', 'penicillin', 'doxycycline', 'streptomycin', 'gentamicin', 'fleming', 'ezcef'],
            13 => ['artemether', 'artesunate', 'coartem', 'lonart', 'lumefantrine', 'chloroquine', 'fansidar', 'felvin'],
            14 => ['metronidazole', 'tinidazole', 'loxagyl', 'fasigyn', 'flagyl'],
            11 => ['metformin', 'glibenclamide', 'insulin', 'glucophage', 'diabetic'],
            10 => ['cetirizine', 'loratadine', 'chlorpheniramine', 'diphenhydramine', 'cypri gold', 'cyproheptadine', 'fexofenadine'],
             9 => ['omeprazole', 'ranitidine', 'lansoprazole', 'pantoprazole', 'gestid', 'maalox', 'gaviscon', 'antacid', 'esomeprazole'],
            21 => ['inhaler', 'salbutamol', 'budesonide', 'ventolin', 'beclomethasone', 'asmalyn'],
            22 => ['eye drop', 'eye oint', 'ophthalmic'],
            25 => ['postinor', 'contraceptive', 'levonorgestrel', 'microgynon', 'jadelle', 'noristerat'],
            24 => ['mebendazole', 'albendazole', 'praziquantel', 'zentel', 'vermox', 'combantrin'],
            23 => ['toothpaste', 'shampoo', 'toiletries', 'dettol', 'sanitary'],
            18 => ['aphrodis', 'libido', 'testosterone'],
             6 => ['syringe', 'cannula', 'catheter', 'gloves', 'bandage', 'plaster', 'consumable'],
             2 => ['injection', 'injectable', 'intravenous', 'ampoule', 'vial'],
            20 => ['hyoscine', 'buscopan', 'scopolamine'],
             5 => ['cough', 'pectol', 'shaltoux', 'expectorant', 'mucolytic', 'bromhexine', 'guaifenesin'],
            17 => ['abidec', 'paediatric multivit', 'pediatric multivit'],
             3 => ['multivitamin', 'astymin', 'astyfer', 'neurovit', 'b-complex', 'folic acid', 'ferrous', 'vitamin'],
             4 => ['supplement', 'herbal', '7keys', 'seven keys', 'omega', 'calcium', 'zinc', 'collagen'],
             7 => ['clotrimazole', 'miconazole', 'nystatin', 'griseofulvin', 'terbinafine', 'fluconazole', 'ketoconazole'],
            12 => ['cream', 'ointment', 'lotion'],
             1 => ['analgesic', 'painkil', 'anacin', 'cenpain', 'paracetamol', 'ibuprofen', 'diclofenac', 'aspirin', 'tramadol', 'para tab', 'lifenol'],
        ];

        foreach ($rules as $categoryId => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($n, $keyword)) {
                    return $categoryId;
                }
            }
        }

        return 4; // Default: Supplements
    }
}
