# AI Content Master (SGE Enabled)

> **WordPress plugin** untuk transformasi content creation dengan AI — dioptimasi untuk Google AI Overviews, SGE, dan modern search landscape.  
> Dibuat oleh [Reynov Christian](https://chrisnov.com) · Chrisnov IT Solutions

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

## 📄 Lisensi

GPL v2 or later — [gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
