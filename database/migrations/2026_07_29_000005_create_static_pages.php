<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('static_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('content');
            $table->boolean('is_published')->default(true)->index();
            $table->timestamps();
        });

        $now = now();
        DB::table('static_pages')->insert([
            [
                'slug' => 'about-us',
                'title' => 'درباره ما',
                'content' => <<<'HTML'
<h2>درباره گیت</h2>
<p>گیت یک موتور جست‌وجو و مرجع معرفی تورها، هتل‌ها، اقامتگاه‌ها و خدمات سفر است. با جمع‌آوری اطلاعات از سایت‌های معتبر و مقایسه آن‌ها، گیت به شما کمک می‌کند بهترین گزینه‌ها را برای سفر خود پیدا کنید.</p>
<p>گیت به‌عنوان یک پلتفرم مستقل تلاش می‌کند اطلاعات کامل و دقیقی از قیمت و خدمات سفر ارائه دهد. شما می‌توانید با انتخاب پیشنهاد موردنظر، به سایت اصلی ارائه‌دهنده هدایت شوید و خرید خود را همان‌جا انجام دهید.</p>
<p>گیت فروشگاه اینترنتی نیست؛ مرجعی برای جست‌وجو، مقایسه و معرفی خدمات گردشگری، تفریحی و سایت‌های معتبر این حوزه است. هدف ما این است که گزینه‌های موجود را یک‌جا ببینید و آگاهانه‌تر انتخاب کنید.</p>
<h2>مأموریت ما</h2>
<p>مأموریت ما در گیت این است که با گردآوری تورها، هتل‌ها، اقامتگاه‌ها و خدمات ویزا از ارائه‌دهندگان معتبر، امکان مقایسه قیمت و شرایط را فراهم کنیم تا با اطمینان بیشتری برای سفر تصمیم بگیرید.</p>
HTML,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'contact-us',
                'title' => 'تماس با ما',
                'content' => <<<'HTML'
<h2>تماس با گیت</h2>
<p>برای ارتباط با تیم گیت می‌توانید از راه‌های زیر استفاده کنید:</p>
<p><strong>تلفن:</strong> <a href="tel:09199010216" dir="ltr">۰۹۱۹۹۰۱۰۲۱۶</a></p>
<p><strong>ایمیل:</strong> <a href="mailto:info@geyt.ir" dir="ltr">info@geyt.ir</a></p>
HTML,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'faq',
                'title' => 'سؤالات متداول',
                'content' => <<<'HTML'
<h2>چگونه می‌توانم از خدمات گیت استفاده کنم؟</h2>
<p>در گیت می‌توانید میان تورها، هتل‌ها، اقامتگاه‌ها و خدمات ویزا جست‌وجو کنید، قیمت‌ها را کنار هم ببینید و پیشنهاد مناسب را انتخاب کنید.</p>
<h2>آیا اطلاعات گیت به‌روز است؟</h2>
<p>بله. اطلاعات قیمت و خدمات به‌صورت مستمر از سایت‌های معتبر جمع‌آوری و به‌روزرسانی می‌شوند.</p>
<h2>آیا استفاده از گیت هزینه دارد؟</h2>
<p>خیر. جست‌وجو و مقایسه پیشنهادها در گیت برای کاربران رایگان است.</p>
<h2>آیا رزرو مستقیماً در گیت انجام می‌شود؟</h2>
<p>خیر. گیت لینک مستقیم پیشنهادها را نمایش می‌دهد و برای تکمیل رزرو یا خرید به سایت اصلی ارائه‌دهنده منتقل می‌شوید.</p>
HTML,
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('static_pages');
    }
};
