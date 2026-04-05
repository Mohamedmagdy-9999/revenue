<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\ServicePrice;

class HealthExcelSeeder extends Seeder
{
    public function run()
    {
        $sheets = Excel::toCollection(null, storage_path('app/health.xlsx'));

        // تأكد إن كل الأنواع موجودة
        $types = [
            'خدمة',
            'تجديد',
            'فرع',
            'تجديد فرع',
            'رسوم الغرامة',
            'مهنة تحسين جديد وتجديد',
            'مهنة تحسين فرع وتجديد فرع',
            'مهنة نظافة جديد وتجديد',
            'مهنة نظافة فرع وتجديد فرع',
            'رسوم سرير',
        ];

        foreach ($types as $type) {
            ServiceType::firstOrCreate(['name' => $type]);
        }

        // Category الصحة فقط
        $category = Category::where('name', 'الصحة')->firstOrFail();

        foreach ($sheets as $sheet) {
            foreach ($sheet as $row) {

                $row = array_values($row->toArray());

                // تجاهل الصفوف الفارغة والهيدر
                if (empty(array_filter($row)) || ($row[1] ?? '') === 'خدمة') {
                    continue;
                }

                // تجاهل عناوين الأقسام (401 - 402 ...)
                if (!is_numeric($row[0] ?? null)) {
                    continue;
                }

                // إنشاء الخدمة
                $service = Service::updateOrCreate(
                    ['code' => trim($row[0])],
                    ['category_id' => $category->id, 'name' => trim($row[1])]
                );

                // حفظ الأسعار (حسب ترتيب أعمدة ملف الصحة)
                $this->savePrice($service, 'خدمة', $row[2] ?? null);
                $this->savePrice($service, 'تجديد', $row[3] ?? null);
                $this->savePrice($service, 'فرع', $row[4] ?? null);
                $this->savePrice($service, 'تجديد فرع', $row[5] ?? null);
                $this->savePrice($service, 'رسوم الغرامة', $row[7] ?? null);

                $this->savePrice($service, 'مهنة تحسين جديد وتجديد', $row[8] ?? null);
                $this->savePrice($service, 'مهنة تحسين فرع وتجديد فرع', $row[9] ?? null);
                $this->savePrice($service, 'مهنة نظافة جديد وتجديد', $row[10] ?? null);
                $this->savePrice($service, 'مهنة نظافة فرع وتجديد فرع', $row[11] ?? null);
                $this->savePrice($service, 'رسوم سرير', $row[12] ?? null);
            }
        }
    }

    private function savePrice($service, $typeName, $price)
    {
        if ($price === null) return;

        // تنظيف القيم (يشيل % وأي حروف)
        $price = preg_replace('/[^0-9.]/', '', $price);

        if ($price === '' || !is_numeric($price) || $price <= 0) {
            return;
        }

        $type = ServiceType::where('name', $typeName)->first();
        if (!$type) return;

        ServicePrice::updateOrCreate(
            [
                'service_id' => $service->id,
                'service_type_id' => $type->id,
            ],
            [
                'price' => $price,
            ]
        );
    }
}
