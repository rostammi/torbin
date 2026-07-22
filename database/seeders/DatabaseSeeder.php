<?php

namespace Database\Seeders;

use App\Models\Tour;
use App\Models\User;
use App\Services\Discovery\ProviderCatalog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@example.com'], [
            'name' => 'مدیر توربین',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $shiraz = Tour::updateOrCreate(['slug' => 'shiraz-tour'], [
            'title' => 'تور شیراز',
            'excerpt' => 'سفر به شهر شعر و باغ‌های ایرانی؛ از حافظیه تا تخت جمشید.',
            'description' => "شیراز شهری است که عطر بهارنارنج، شعر و تاریخ را یک‌جا دارد. در این تور می‌توانید از حافظیه، سعدیه، باغ ارم، مسجد نصیرالملک و مجموعه جهانی تخت جمشید دیدن کنید.\n\nقیمت فروشنده‌ها را مقایسه کنید و پیشنهاد مناسب خودتان را مستقیم از ارائه‌دهنده بخرید.",
            'is_active' => true,
        ]);

        $kish = Tour::updateOrCreate(['slug' => 'kish-tour'], [
            'title' => 'تور کیش',
            'excerpt' => 'چند روز آرامش کنار خلیج فارس با تفریحات دریایی و خرید.',
            'description' => 'جزیره کیش با ساحل‌های آرام، آب شفاف و تفریحات متنوع یکی از محبوب‌ترین مقصدهای ایران است. پیشنهادهای چند فروشنده را کنار هم ببینید و بر اساس قیمت انتخاب کنید.',
            'is_active' => true,
        ]);

        $this->seedOfficialSources($shiraz);
        $this->seedOfficialSources($kish);
    }

    private function seedOfficialSources(Tour $tour): void
    {
        $destination = preg_replace('/^تور(?:های)?\s+/u', '', $tour->title) ?: $tour->title;
        app(ProviderCatalog::class)->attach($tour, $destination, 10);
    }
}
