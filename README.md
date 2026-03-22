# AI Content Master (SGE Enabled)

> **WordPress plugin** untuk transformasi content creation dengan AI — dioptimasi untuk Google AI Overviews, SGE, dan modern search landscape.  
> Dibuat oleh [Reynov Christian](https://chrisnov.com) · Chrisnov IT Solutions

![Version](https://img.shields.io/badge/version-1.3.0-blue) ![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-21759b) ![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4) ![License](https://img.shields.io/badge/license-GPL%20v2-green)

---

## ✨ Fitur Utama

### ✍️ Content Generation
- **SGE Article Generator** — Generate artikel lengkap yang dioptimasi untuk AI Overviews. Mencakup direct answer block, sinyal E-E-A-T, dan struktur heading yang ramah AI search.
- **Article Rewriter** — Tulis ulang seluruh artikel secara menyeluruh sambil mempertahankan pesan inti dan fakta-fakta kunci.
- **Text Rephraser** — Pilih satu blok paragraf (Gutenberg) atau highlight teks (Classic Editor) untuk langsung di-rephrase dengan alur yang lebih baik.

### 🔍 SEO & Analysis
- **AI Search Analyzer** — Analisis mendalam konten untuk potensi SGE Featured Snippet, kesiapan E-E-A-T, dan saran optimasi People Also Ask.
- **High-CTR Meta Generator** — Generate meta description yang dirancang untuk mendorong klik dari ringkasan AI dan hasil pencarian tradisional.

### 🤖 Smart Model Selector *(Baru di v1.1)*
- **Dynamic Dropdown** — Fetch model langsung dari OpenRouter API, sorted otomatis: **Free models di atas**, Paid di bawah.
- **Model Info Bar** — Badge FREE/PAID + context window size (misal `1M tokens`, `128K tokens`) tampil real-time saat model dipilih.
- **Refresh Models Button** — Invalidate cache dan rebuild dropdown in-place tanpa reload halaman.
- **Free-first sorting** — Free models di-sort alfabetis dalam `optgroup` tersendiri, begitu juga Paid models.

### 🔌 OpenRouter Integration
Akses ratusan model AI terbaik (Gemini, GPT-4o, Claude, Llama, Mistral, dll.) melalui **satu API key**. Model gratis tersedia tanpa biaya tambahan.

---

## 🛠️ Instalasi

1. Upload folder `ai-content-master` ke direktori `/wp-content/plugins/` pada instalasi WordPress kamu.
2. Aktifkan plugin melalui menu **Plugins**.
3. Buka **Settings → AI Content Master** untuk memasukkan OpenRouter API Key.
4. Pilih model AI dari dropdown — model gratis sudah tersedia di bagian paling atas.
5. Buka post/page editor — gunakan meta box **"AI Content Master"** di sidebar.

> **Cara mendapatkan API Key:** Daftar gratis di [openrouter.ai/keys](https://openrouter.ai/keys). Tidak perlu kartu kredit untuk menggunakan model gratis.

---

## 📁 Struktur Proyek

```
ai-content-master/
├── ai-content-master.php          # Plugin entry point, constants, autoloader init
├── css/
│   └── admin.css                  # Admin styles (settings page + meta box)
├── js/
│   ├── admin.js                   # Script untuk post editor (meta box AJAX)
│   └── settings.js                # Script untuk settings page (model selector)
├── includes/
│   ├── class-ai-content-master.php    # Main plugin class (Singleton)
│   ├── class-autoloader.php           # PSR-style autoloader
│   ├── admin/
│   │   ├── class-admin-meta-box.php   # Meta box di post/page editor
│   │   ├── class-admin-scripts.php    # Enqueue scripts & styles
│   │   └── class-admin-settings.php   # Settings page & model selector
│   ├── api/
│   │   └── class-openrouter-api.php   # OpenRouter API handler + model fetcher
│   └── features/
│       ├── class-article-generator.php  # SGE article generation
│       ├── class-article-rewriter.php   # Full article rewrite
│       ├── class-meta-generator.php     # Meta description generator
│       ├── class-seo-analyzer.php       # AI search / SGE analysis
│       └── class-text-rephraser.php     # Block/selection rephraser
└── README.md
```

---

## ⚙️ Persyaratan

| Komponen | Versi Minimum |
|----------|---------------|
| WordPress | 6.4+ |
| PHP | 8.0+ |
| OpenRouter API Key | Gratis ([daftar di sini](https://openrouter.ai/keys)) |

---

## 🔒 Keamanan

- Semua AJAX request dilindungi dengan **WordPress Nonce** (`ai_content_master_ajax_nonce`)
- Input disanitasi menggunakan `sanitize_text_field()` dan `wp_unslash()`
- Capability check (`edit_posts`, `manage_options`) pada setiap handler
- Rate limiting per user: 1 request per 30 detik
- API key disimpan via WordPress options, tidak pernah di-expose ke frontend

---

## 🤖 Strategi AI Search (SGE)

Plugin ini mengimplementasikan pendekatan terbaru dalam AI search optimization:

- **Direct Answer Block** — Setiap artikel dimulai dengan ringkasan 40–60 kata yang menjadi kandidat kuat untuk AI Overview snippet.
- **Semantic Connectivity** — Konten mencakup entitas terkait dan menjawab pertanyaan "People Also Ask" secara natural.
- **E-E-A-T Proofing** — Prompt engineering mendorong penggunaan data, pengalaman langsung, dan klaim autoritatif yang membedakan konten dari pure AI-generated text.

---

## 👨‍💻 Author

**Reynov Christian** — [chrisnov.com](https://chrisnov.com)  
Chrisnov IT Solutions · Ruteng, Flores, NTT

## 📋 Changelog

### v1.3.0 *(Maret 2026)*
- ✨ **Multi-provider architecture** — OpenRouter + Google Gemini AI Studio as providers
- ✨ **Multi-model fallback** — automatic fallback chain across free models on 429/timeout
- ✨ **Per-model blacklisting** — rate-limited models skipped for 15 min, zero manual action needed
- ✨ **Provider Manager** — single entry point, auto-resolves active provider from settings
- 🔧 **Fix: Markdown artefacts** — strip ` ```html ` fences, `**bold**`, `*italic*` from AI output
- 🔧 **Fix: PHP 8.2+ deprecation** — declare `$prev_socket_timeout` explicitly in base class
- 🔧 **Fix: Gemini deprecated models** — update to Gemini 2.5 Flash / Gemini 3 Flash Preview
- 🔧 **Fix: dashicons JS dependency** — enqueue as stylesheet separately (WP 6.9.1 strict)
- 📝 **Updated fallback model list** — Llama 3.3, Gemma 3 27B, Mistral Small 3.1, DeepSeek V3, Phi-4, QwQ 32B

### v1.2.0 *(Maret 2026)*
- ✨ **Smart Model Selector** — fetch live dari OpenRouter, free models di atas, badge FREE/PAID + context window
- ✨ **Refresh Models button** — invalidate cache & rebuild dropdown via AJAX tanpa reload
- ✨ **Test Connection button** — ping test ke OpenRouter langsung dari settings page
- 🔧 **Fix: Nonce mismatch** — seragamkan semua AJAX handler ke `ai_content_master_ajax_nonce`
- 🔧 **Fix: Memory exhaustion** — lazy API initialization, hapus circular dependency di constructor
- 🔧 **Fix: Double `init()`** — refactor ke `init_component()` terpusat
- 🔧 **Fix: default_socket_timeout** — override per-request untuk handle slow free models
- 🔧 **Fix: AJAX double-submit** — button disabled + `activeRequests` guard selama request berlangsung
- 🔧 **Fix: Rephrase feature** — tambah Classic Editor fallback via `tinymce.selection`
- 🔧 **Fix: Default model** — ganti ke `google/gemini-2.0-flash-001:free` (model lama invalid)
- 📝 **Upgrade prompt** Article Generator — struktur SGE lengkap: Quick Answer block, FAQ schema-ready, code snippet support
- 📝 **Tambah `css/admin.css`** dan **`js/settings.js`** untuk settings page
- 📝 **Tambah ROADMAP.md** — rencana pengembangan berbasis WP 6.9 AI Building Blocks

### v1.0.0 *(Rilis Awal)*
- 🚀 5 fitur utama: Article Generator, Article Rewriter, SEO Analyzer, Meta Generator, Text Rephraser
- 🔌 Integrasi OpenRouter API
- 🧩 Arsitektur modular (Singleton, Autoloader, komponen terpisah)

---

## 📄 Lisensi

GPL v2 or later — [gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
