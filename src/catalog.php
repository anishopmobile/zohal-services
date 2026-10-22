<?php
declare(strict_types=1);

/** Adding a service only requires a new definition, an adapter mapping and a route in admin. */
return [
    'shahkar' => ['title' => 'شاهکار (تطابق موبایل و کد ملی)', 'category' => 'هویتی', 'icon' => '🛡️', 'subtitle' => 'بررسی انطباق مالک سیم‌کارت با کد ملی', 'fields' => [
        'national_code' => ['label'=>'کد ملی', 'rule'=>'national_code', 'placeholder'=>'۰۰۱۲۳۴۵۶۷۸'],
        'mobile' => ['label'=>'شماره موبایل', 'rule'=>'mobile', 'placeholder'=>'۰۹۱۲۱۲۳۴۵۶۷'],
    ]],
    'account_to_iban' => ['title'=>'تبدیل حساب به شبا', 'category'=>'بانکی', 'icon'=>'🏦', 'subtitle'=>'محاسبه شماره شبا از روی شماره حساب', 'fields'=>[
        'account' => ['label'=>'شماره حساب', 'rule'=>'account', 'placeholder'=>'شماره حساب بانکی'],
        'bank' => ['label'=>'کد بانک', 'rule'=>'bank', 'placeholder'=>'کد بانک طبق مستندات زحل'],
    ]],
    'card_to_account' => ['title'=>'تبدیل کارت به حساب', 'category'=>'بانکی', 'icon'=>'💳', 'subtitle'=>'بازیابی شماره حساب مرتبط با کارت', 'fields'=>[
        'card'=>['label'=>'شماره کارت', 'rule'=>'card', 'placeholder'=>'۱۶ رقم شماره کارت'],
    ]],
    'card_to_iban' => ['title'=>'تبدیل کارت به شبا', 'category'=>'بانکی', 'icon'=>'💸', 'subtitle'=>'دریافت شماره شبا از روی کارت', 'fields'=>[
        'card'=>['label'=>'شماره کارت', 'rule'=>'card', 'placeholder'=>'۱۶ رقم شماره کارت'],
    ]],
    'iban_inquiry' => ['title'=>'استعلام شبا', 'category'=>'بانکی', 'icon'=>'🔎', 'subtitle'=>'بررسی و استعلام اطلاعات شماره شبا', 'fields'=>[
        'iban'=>['label'=>'شماره شبا', 'rule'=>'iban', 'placeholder'=>'IR۰۰۱۲۳۴۵۶۷۸۹۰۱۲۳۴۵۶۷۸۹۰۱۲'],
    ]],
    'postal_code' => ['title'=>'استعلام کد پستی', 'category'=>'خدماتی', 'icon'=>'📍', 'subtitle'=>'استعلام اطلاعات مربوط به کد پستی', 'fields'=>[
        'postal_code'=>['label'=>'کد پستی', 'rule'=>'postal_code', 'placeholder'=>'کد پستی ۱۰ رقمی'],
    ]],
    'vehicle_fines' => ['title'=>'استعلام خلافی خودرو (API)', 'category'=>'خدماتی', 'icon'=>'🚘', 'subtitle'=>'استعلام مجموع خلافی خودرو', 'fields'=>[
        'plate'=>['label'=>'پلاک خودرو', 'rule'=>'plate', 'placeholder'=>'پلاک کامل طبق فرمت مورد نیاز سرویس'],
    ]],
    'vehicle_fines_detailed' => ['title'=>'خلافی خودرو با جزئیات (API)', 'category'=>'خدماتی', 'icon'=>'📋', 'subtitle'=>'فهرست و جزئیات جرایم خودرو', 'fields'=>[
        'plate'=>['label'=>'پلاک خودرو', 'rule'=>'plate', 'placeholder'=>'پلاک کامل طبق فرمت مورد نیاز سرویس'],
    ]],
    'bank_credit' => ['title'=>'اعتبارسنجی بانکی (API)', 'category'=>'بانکی', 'icon'=>'📈', 'subtitle'=>'درخواست گزارش اعتبارسنجی بانکی', 'fields'=>[
        'national_code'=>['label'=>'کد ملی', 'rule'=>'national_code', 'placeholder'=>'۱۰ رقم کد ملی'],
        'mobile'=>['label'=>'شماره همراه', 'rule'=>'mobile', 'placeholder'=>'شماره همراه متقاضی'],
    ]],
];
