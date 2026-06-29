<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrackingStatus extends Model
{
    use HasFactory;

    public $table = 'tracking_statuses';

    // الحالات التفصيلية (25+ حالة)
    const STATUSES = [
        // ── إنشاء الطلب ──────────────────────────────────────────
        ['code' => 'ORDER_CREATED',            'name_ar' => 'تم إنشاء الطلب',                 'name_en' => 'Order Created',                  'color' => '#6c757d', 'sort_order' => 1,  'is_final' => false, 'category' => 'created',   'notify_customer' => true],
        ['code' => 'PENDING_PICKUP',           'name_ar' => 'بانتظار الاستلام من المرسل',      'name_en' => 'Pending Pickup',                 'color' => '#fd7e14', 'sort_order' => 2,  'is_final' => false, 'category' => 'pickup',    'notify_customer' => true],
        ['code' => 'COURIER_ASSIGNED_PICKUP',  'name_ar' => 'تم تعيين مندوب الاستلام',         'name_en' => 'Courier Assigned for Pickup',    'color' => '#fd7e14', 'sort_order' => 3,  'is_final' => false, 'category' => 'pickup',    'notify_customer' => true],
        ['code' => 'COURIER_ON_WAY_PICKUP',    'name_ar' => 'المندوب في الطريق للاستلام',      'name_en' => 'Courier On Way to Pickup',       'color' => '#ffc107', 'sort_order' => 4,  'is_final' => false, 'category' => 'pickup',    'notify_customer' => true],
        // ── الاستلام ─────────────────────────────────────────────
        ['code' => 'PICKED_UP',                'name_ar' => 'تم الاستلام من المرسل',           'name_en' => 'Picked Up',                      'color' => '#0dcaf0', 'sort_order' => 5,  'is_final' => false, 'category' => 'pickup',    'notify_customer' => true,  'notify_sender' => true],
        ['code' => 'AT_ORIGIN_BRANCH',         'name_ar' => 'وصل فرع الإرسال',                'name_en' => 'At Origin Branch',               'color' => '#0d6efd', 'sort_order' => 6,  'is_final' => false, 'category' => 'transit',   'notify_customer' => false],
        ['code' => 'SORTED_AT_ORIGIN',         'name_ar' => 'تم الفرز في فرع الإرسال',        'name_en' => 'Sorted at Origin',               'color' => '#0d6efd', 'sort_order' => 7,  'is_final' => false, 'category' => 'transit',   'notify_customer' => false],
        // ── العبور ───────────────────────────────────────────────
        ['code' => 'TRANSFERRED_TO_CARRIER',   'name_ar' => 'محوَّل لشركة الشحن الناقلة',     'name_en' => 'Transferred to Carrier',         'color' => '#6f42c1', 'sort_order' => 8,  'is_final' => false, 'category' => 'carrier',   'notify_customer' => true],
        ['code' => 'IN_TRANSIT',               'name_ar' => 'في الطريق',                       'name_en' => 'In Transit',                     'color' => '#0d6efd', 'sort_order' => 9,  'is_final' => false, 'category' => 'transit',   'notify_customer' => false],
        ['code' => 'AT_HUB',                   'name_ar' => 'في مركز الفرز الرئيسي',          'name_en' => 'At Hub',                         'color' => '#0d6efd', 'sort_order' => 10, 'is_final' => false, 'category' => 'transit',   'notify_customer' => false],
        ['code' => 'CUSTOMS_CLEARANCE',        'name_ar' => 'في إجراءات الجمارك',             'name_en' => 'Customs Clearance',              'color' => '#e83e8c', 'sort_order' => 11, 'is_final' => false, 'category' => 'transit',   'notify_customer' => true],
        ['code' => 'CUSTOMS_CLEARED',          'name_ar' => 'تم إخلاء الجمارك',              'name_en' => 'Customs Cleared',                'color' => '#20c997', 'sort_order' => 12, 'is_final' => false, 'category' => 'transit',   'notify_customer' => true],
        // ── الوصول لفرع الوجهة ───────────────────────────────────
        ['code' => 'AT_DESTINATION_BRANCH',    'name_ar' => 'وصل فرع الوجهة',                 'name_en' => 'At Destination Branch',          'color' => '#198754', 'sort_order' => 13, 'is_final' => false, 'category' => 'delivery',  'notify_customer' => true],
        ['code' => 'SORTED_AT_DESTINATION',    'name_ar' => 'تم الفرز في فرع الوجهة',         'name_en' => 'Sorted at Destination',          'color' => '#198754', 'sort_order' => 14, 'is_final' => false, 'category' => 'delivery',  'notify_customer' => false],
        // ── التسليم ───────────────────────────────────────────────
        ['code' => 'COURIER_ASSIGNED_DELIVERY','name_ar' => 'تم تعيين مندوب التسليم',          'name_en' => 'Courier Assigned for Delivery',  'color' => '#20c997', 'sort_order' => 15, 'is_final' => false, 'category' => 'delivery',  'notify_customer' => true],
        ['code' => 'OUT_FOR_DELIVERY',         'name_ar' => 'خرج للتسليم',                    'name_en' => 'Out for Delivery',               'color' => '#20c997', 'sort_order' => 16, 'is_final' => false, 'category' => 'delivery',  'notify_customer' => true],
        ['code' => 'DELIVERY_ATTEMPTED',       'name_ar' => 'محاولة تسليم - لم يُستجَب',      'name_en' => 'Delivery Attempted',             'color' => '#ffc107', 'sort_order' => 17, 'is_final' => false, 'category' => 'exception', 'notify_customer' => true],
        ['code' => 'DELIVERED',                'name_ar' => 'تم التسليم',                      'name_en' => 'Delivered',                      'color' => '#198754', 'sort_order' => 18, 'is_final' => true,  'category' => 'delivery',  'notify_customer' => true,  'notify_sender' => true],
        // ── الاستثناءات ───────────────────────────────────────────
        ['code' => 'FAILED_DELIVERY',          'name_ar' => 'فشل التسليم',                    'name_en' => 'Failed Delivery',                'color' => '#dc3545', 'sort_order' => 19, 'is_final' => false, 'category' => 'exception', 'notify_customer' => true,  'notify_sender' => true],
        ['code' => 'ADDRESS_NOT_FOUND',        'name_ar' => 'العنوان غير موجود',              'name_en' => 'Address Not Found',              'color' => '#dc3545', 'sort_order' => 20, 'is_final' => false, 'category' => 'exception', 'notify_customer' => true,  'notify_sender' => true],
        ['code' => 'CUSTOMER_NOT_AVAILABLE',   'name_ar' => 'العميل غير متوفر',               'name_en' => 'Customer Not Available',         'color' => '#ffc107', 'sort_order' => 21, 'is_final' => false, 'category' => 'exception', 'notify_customer' => true],
        ['code' => 'ON_HOLD',                  'name_ar' => 'موقوفة مؤقتاً',                  'name_en' => 'On Hold',                        'color' => '#ffc107', 'sort_order' => 22, 'is_final' => false, 'category' => 'exception', 'notify_customer' => true],
        ['code' => 'SHIPMENT_DAMAGED',         'name_ar' => 'الشحنة تالفة',                   'name_en' => 'Shipment Damaged',               'color' => '#dc3545', 'sort_order' => 23, 'is_final' => false, 'category' => 'exception', 'notify_customer' => true,  'notify_sender' => true],
        ['code' => 'SHIPMENT_LOST',            'name_ar' => 'الشحنة مفقودة',                  'name_en' => 'Shipment Lost',                  'color' => '#dc3545', 'sort_order' => 24, 'is_final' => false, 'category' => 'exception', 'notify_customer' => true,  'notify_sender' => true],
        // ── الإرجاع ───────────────────────────────────────────────
        ['code' => 'RETURNED_TO_BRANCH',       'name_ar' => 'مُعاد للفرع',                    'name_en' => 'Returned to Branch',             'color' => '#ffc107', 'sort_order' => 25, 'is_final' => false, 'category' => 'returned',  'notify_sender' => true],
        ['code' => 'RETURN_IN_TRANSIT',        'name_ar' => 'في طريق الإرجاع',               'name_en' => 'Return In Transit',              'color' => '#ffc107', 'sort_order' => 26, 'is_final' => false, 'category' => 'returned',  'notify_sender' => true],
        ['code' => 'RETURNED_TO_SENDER',       'name_ar' => 'مُعاد للمرسل',                   'name_en' => 'Returned to Sender',             'color' => '#dc3545', 'sort_order' => 27, 'is_final' => true,  'category' => 'returned',  'notify_customer' => false, 'notify_sender' => true],
        ['code' => 'CANCELLED',                'name_ar' => 'ملغي',                           'name_en' => 'Cancelled',                      'color' => '#dc3545', 'sort_order' => 28, 'is_final' => true,  'category' => 'created',   'notify_customer' => true,  'notify_sender' => true],
    ];

    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'icon',
        'color',
        'sort_order',
        'is_final',
        'is_active',
    ];

    protected $casts = [
        'is_final'  => 'boolean',
        'is_active' => 'boolean',
    ];

    public function trackingRecords()
    {
        return $this->hasMany(ShipmentTracking::class, 'tracking_status_id');
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'en' ? $this->name_en : $this->name_ar;
    }
}
