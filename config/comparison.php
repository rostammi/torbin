<?php

return [
    'provider_slugs' => [
        'علیبابا' => 'alibaba',
    ],

    'categories' => [
        'tour' => [
            'label' => 'تور',
            'plural' => 'تورها',
            'route' => 'tours',
            'icon' => '✈',
            'hero' => 'قیمت تورها را یک‌جا مقایسه کنید',
        ],
        'hotel' => [
            'label' => 'هتل',
            'plural' => 'هتل‌ها',
            'route' => 'hotels',
            'icon' => '▥',
            'hero' => 'قیمت هتل‌ها را یک‌جا مقایسه کنید',
        ],
        'stay' => [
            'label' => 'اقامتگاه',
            'plural' => 'اقامتگاه‌ها',
            'route' => 'stays',
            'icon' => '⌂',
            'hero' => 'قیمت اقامتگاه‌ها را یک‌جا مقایسه کنید',
        ],
        'visa' => [
            'label' => 'ویزا',
            'plural' => 'ویزاها',
            'route' => 'visas',
            'icon' => '▣',
            'hero' => 'هزینه خدمات ویزا را یک‌جا مقایسه کنید',
        ],
    ],

    'catalogs' => [
        'hotel' => [
            'تهران', 'مشهد', 'کیش', 'قشم', 'شیراز', 'اصفهان', 'تبریز', 'رشت',
            'رامسر', 'چالوس', 'یزد', 'کرمان', 'بندرعباس', 'اردبیل', 'همدان',
        ],
        'stay' => [
            'کیش', 'قشم', 'ماسال', 'رامسر', 'رشت', 'لاهیجان', 'کاشان', 'یزد',
            'شیراز', 'اصفهان', 'چابهار', 'کویر مرنجاب', 'کلاردشت', 'سرعین', 'جزیره هرمز',
        ],
        'visa' => [
            'دبی', 'عمان', 'قطر', 'ترکیه', 'روسیه', 'چین', 'هند', 'تایلند',
            'ژاپن', 'کانادا', 'استرالیا', 'فرانسه', 'ایتالیا', 'اسپانیا', 'آلمان',
        ],
    ],

    'providers' => [
        'hotel' => [
            ['name' => 'علی‌بابا هتل', 'type' => 'marketplace_html', 'url' => 'https://www.alibaba.ir/hotel'],
            ['name' => 'جاباما', 'type' => 'marketplace_html', 'url' => 'https://www.jabama.com/'],
            ['name' => 'اقامت ۲۴', 'type' => 'marketplace_html', 'url' => 'https://www.eghamat24.com/'],
            ['name' => 'ایران هتل آنلاین', 'type' => 'marketplace_html', 'url' => 'https://www.iranhotelonline.com/'],
            ['name' => 'اسنپ‌تریپ', 'type' => 'marketplace_html', 'url' => 'https://www.snapptrip.com/'],
            ['name' => 'فلای‌تودی هتل', 'type' => 'marketplace_html', 'url' => 'https://www.flytoday.ir/hotel'],
            ['name' => 'مستر بلیط هتل', 'type' => 'marketplace_html', 'url' => 'https://mrbilit.com/hotel'],
            ['name' => 'رسپینا هتل', 'type' => 'marketplace_html', 'url' => 'https://respina24.ir/hotel'],
            ['name' => 'الی‌گشت هتل', 'type' => 'marketplace_html', 'url' => 'https://www.eligasht.com/hotels/'],
            ['name' => 'بوکینگ ایران', 'type' => 'marketplace_html', 'url' => 'https://www.booking.ir/'],
        ],
        'stay' => [
            ['name' => 'جاباما', 'type' => 'marketplace_html', 'url' => 'https://www.jabama.com/'],
            ['name' => 'شب', 'type' => 'marketplace_html', 'url' => 'https://www.shab.ir/'],
            ['name' => 'هومسا', 'type' => 'marketplace_html', 'url' => 'https://www.homsa.net/'],
            ['name' => 'اتاقک', 'type' => 'marketplace_html', 'url' => 'https://www.otaghak.com/'],
            ['name' => 'جاجیگا', 'type' => 'marketplace_html', 'url' => 'https://www.jajiga.com/'],
            ['name' => 'میهمان‌شو', 'type' => 'marketplace_html', 'url' => 'https://www.mihmansho.com/'],
            ['name' => 'سپنجا', 'type' => 'marketplace_html', 'url' => 'https://sepanja.com/'],
            ['name' => 'جاجوریم', 'type' => 'marketplace_html', 'url' => 'https://jajurim.com/'],
            ['name' => 'اقامت ۲۴', 'type' => 'marketplace_html', 'url' => 'https://www.eghamat24.com/'],
            ['name' => 'اسنپ‌تریپ اقامتگاه', 'type' => 'marketplace_html', 'url' => 'https://www.snapptrip.com/'],
        ],
        'visa' => [
            ['name' => 'بابیا ویزا', 'type' => 'marketplace_html', 'url' => 'https://babia.ir/visas/'],
            ['name' => 'ویزالند', 'type' => 'marketplace_html', 'url' => 'https://visaland.org/'],
            ['name' => 'ویزا پارک', 'type' => 'marketplace_html', 'url' => 'https://www.visapark.ir/'],
            ['name' => 'ای‌ویزا', 'type' => 'marketplace_html', 'url' => 'https://evisa.ir/'],
            ['name' => '۲۴ ویزا', 'type' => 'marketplace_html', 'url' => 'https://www.24visas.com/'],
            ['name' => 'قصران گشت ویزا', 'type' => 'marketplace_html', 'url' => 'https://www.ghasrangasht.com/visa'],
            ['name' => 'نهال‌گشت ویزا', 'type' => 'marketplace_html', 'url' => 'https://nahalgasht.com/visa/'],
            ['name' => 'الی‌گشت ویزا', 'type' => 'marketplace_html', 'url' => 'https://www.eligasht.com/visa/'],
            ['name' => 'لست‌سکند ویزا', 'type' => 'marketplace_html', 'url' => 'https://lastsecond.ir/visa'],
            ['name' => 'سفر هالیدی ویزا', 'type' => 'marketplace_html', 'url' => 'https://safarholiday.com/visa'],
        ],
    ],

    'fallback_providers' => [
        'hotel' => [
            ['name' => 'هتل‌یار', 'type' => 'marketplace_html', 'url' => 'https://hotelyar.com/'],
            ['name' => 'هتل‌بانک', 'type' => 'marketplace_html', 'url' => 'https://hotelbank.ir/'],
            ['name' => 'پینورست هتل', 'type' => 'marketplace_html', 'url' => 'https://pinorest.com/'],
        ],
        'stay' => [
            ['name' => 'پینورست', 'type' => 'marketplace_html', 'url' => 'https://pinorest.com/'],
            ['name' => 'ویلایار', 'type' => 'marketplace_html', 'url' => 'https://vilayar.com/'],
            ['name' => 'مکانچی', 'type' => 'marketplace_html', 'url' => 'https://makanche.com/'],
        ],
        'visa' => [
            ['name' => 'ویزا موندیال', 'type' => 'marketplace_html', 'url' => 'https://www.visamondial.com/'],
            ['name' => 'سفیران سروش سعادت', 'type' => 'marketplace_html', 'url' => 'https://emigrationplus.com/'],
            ['name' => 'مای آرمان', 'type' => 'marketplace_html', 'url' => 'https://myarman.com/visa/'],
        ],
    ],
];
