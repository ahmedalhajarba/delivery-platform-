# Customer API Handover Package

هذه الملفات تسليم مباشر للزبون لبدء الربط التقني مع المنصة.

## الملفات

- customer-api.postman_collection.json
- customer-api.postman_environment.json

## خطوات التسليم للزبون

1. ادخل لوحة الادارة ثم افتح: /admin/technical-integrations
2. انشئ عميل ربط تقني جديد للزبون.
3. انسخ المفتاح الذي يظهر مرة واحدة بعد الانشاء.
4. ارسل للزبون الملفين اعلاه + المفتاح عبر قناة امنة.

## خطوات الزبون في Postman

1. Import للـ Collection.
2. Import للـ Environment.
3. تعديل:
   - baseUrl
   - clientKey
4. تشغيل الطلبات بالترتيب:
   - Orders - List
   - Orders - Create
   - Tracking - Single
   - Platform Services - Catalog
   - Platform Services - Purchase Subscription
   - Subscriptions - List

## ملاحظة امنية

لا يتم حفظ المفتاح بنصه الكامل في النظام، لذلك عند فقدانه يجب اعادة توليده من لوحة الادارة.
