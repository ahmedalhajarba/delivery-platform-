<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        // تم تعطيل هذا الـmigration نهائياً ويجب أن يبقى فارغاً بعد نقل جدول receipts إلى قاعدة بيانات newp2
    }

    public function down(): void
    {
        // لا يوجد أي عملية حذف مطلوبة هنا
    }
};
