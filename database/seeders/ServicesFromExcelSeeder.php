<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\ServicePrice;

class ServicesFromExcelSeeder extends Seeder
{
    public function run()
    {
        $sheets = Excel::toCollection(null, storage_path('app/services.xlsx'));

        // أنواع الخدمة
        $types = ['خدمة', 'تجديد', 'فرع', 'تجديد فرع','رسوم الغرامة','مهنة تحسين جديد وتجديد','مهنة تحسين فرع وتجديد فرع','مهنة نظافة جديد وتجديد','مهنة نظافة فرع وتجديد فرع'];
        foreach ($types as $type) {
            ServiceType::firstOrCreate(['name' => $type]);
        }

        // Categories ثابتة (بنفس الـ IDs)
        $categories = Category::whereIn('name', [
            'الاشغال',
            'الثقافة',
            'السياحة'
        ])->get()->keyBy('name');

        $currentCategory = null;

        foreach ($sheets as $sheet) {
            foreach ($sheet as $row) {

                $row = array_values($row->toArray());

                // تجاهل الصفوف الفارغة
                if (empty(array_filter($row))) {
                    continue;
                }

                // تجاهل الهيدر
                if (($row[1] ?? '') === 'خدمة') {
                    continue;
                }

                /**
                 * =====================
                 * Section Header
                 * =====================
                 * مثال:
                 * A: 30
                 * B: اشغال
                 */
                if (
                    empty($row[2]) &&
                    isset($categories[trim($row[1] ?? '')])
                ) {
                    $currentCategory = $categories[trim($row[1])];
                    continue;
                }

                /**
                 * =====================
                 * Service Row
                 * =====================
                 */
                if (
                    $currentCategory &&
                    is_numeric($row[0] ?? null) &&
                    !empty($row[1])
                ) {
                    $service = Service::create([
                        'category_id' => $currentCategory->id,
                        'code' => trim($row[0]),
                        'name' => trim($row[1]),
                    ]);

                    $this->savePrice($service, 'خدمة', $row[2] ?? null);
                    $this->savePrice($service, 'تجديد', $row[3] ?? null);
                    $this->savePrice($service, 'فرع', $row[4] ?? null);
                    $this->savePrice($service, 'تجديد فرع', $row[5] ?? null);

                    $this->savePrice($service, 'رسوم الغرامة', $row[6] ?? null);
                    $this->savePrice($service, 'مهنة تحسين جديد وتجديد', $row[7] ?? null);
                    $this->savePrice($service, 'مهنة تحسين فرع وتجديد فرع', $row[8] ?? null);
                    $this->savePrice($service, 'مهنة نظافة جديد وتجديد', $row[9] ?? null);
                    $this->savePrice($service, 'مهنة نظافة فرع وتجديد فرع', $row[10] ?? null);
                }
            }
        }
    }

    private function savePrice($service, $typeName, $price)
    {
        if (!is_numeric($price) || $price <= 0) {
            return;
        }

        $type = ServiceType::where('name', $typeName)->first();

        ServicePrice::create([
            'service_id' => $service->id,
            'service_type_id' => $type->id,
            'price' => $price,
        ]);
    }
}
