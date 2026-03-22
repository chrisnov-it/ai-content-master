# 🗺️ ROADMAP — AI Content Master

> Dokumen ini merangkum rencana pengembangan plugin **AI Content Master** berdasarkan:
> - Hasil sesi development & bug fixing (v1.1)
> - Fitur baru **WordPress 6.9 "Gene"** yang relevan untuk plugin AI
> - Ekosistem **AI Building Blocks for WordPress** (Abilities API, WP AI Client, MCP Adapter)
>
> Diperbarui: **Maret 2026** · Maintainer: Reynov Christian · Chrisnov IT Solutions

---

## ✅ Sudah Selesai (v1.0 → v1.1)

- [x] Plugin struktur modular (Singleton, Autoloader, komponen terpisah)
- [x] Integrasi OpenRouter API (chat completions endpoint)
- [x] 5 fitur utama: Article Generator, Article Rewriter, SEO Analyzer, Meta Generator, Text Rephraser
- [x] Meta box di post/page editor (Gutenberg + Classic Editor fallback)
- [x] **Fix: Nonce mismatch** — menyeragamkan semua AJAX handler ke `ai_content_master_ajax_nonce`
- [x] **Fix: Double `init()`** — refactor ke method `init_component()` terpusat
- [x] **Fix: `delete_transient()` tertinggal** di production code
- [x] **Fix: Default model invalid** → diganti ke `google/gemini-2.0-flash-001:free`
- [x] **Fix: Rephrase feature** — tambah fallback Classic Editor via `tinymce.selection`
- [x] **Fitur: Smart Model Selector** — fetch live dari OpenRouter, sorted free-first
- [x] **Fitur: Model Info Bar** — badge FREE/PAID + context window size
- [x] **Fitur: Refresh Models button** — invalidate cache + rebuild dropdown via AJAX
- [x] Tambah `css/admin.css` dan `js/settings.js`
- [x] Enqueue CSS/JS terpisah per halaman (settings vs. post editor)

---

## 🚧 Fase 1 — Stabilisasi & UX (Target: v1.2)

> Setelah pengujian lokal (LocalWP) selesai dan bug potensial ditemukan.

- [ ] **Fix bugs dari hasil testing lokal** — daftar bug akan diupdate setelah pengujian
- [ ] **Streaming response** — ganti polling AJAX biasa dengan `EventSource` (Server-Sent Events) agar user bisa melihat output AI muncul karakter demi karakter seperti di ChatGPT
  - Butuh endpoint SSE di PHP + `EventSource` listener di JS
  - OpenRouter sudah support streaming via `stream: true`
- [ ] **Loading state yang lebih baik** — progress indicator selama generate artikel panjang
- [ ] **Toast notification** — ganti `alert()` dan inline status text dengan sistem notifikasi yang lebih rapi
- [ ] **Copy-to-clipboard** untuk hasil Meta Description

---

## ⚡ Fase 2 — Fitur Produktivitas (Target: v1.3)

- [ ] **Content History / Versioning** — simpan setiap hasil generate ke `post_meta` sebagai draft tersembunyi, user bisa restore versi sebelumnya
- [ ] **Bulk Article Generator** — generate konten untuk beberapa post sekaligus dari daftar topik (batch processing)
- [ ] **Custom Prompt Templates** — user bisa membuat dan menyimpan template prompt sendiri per kategori konten (Tutorial, Review, Listicle, dll.)
- [ ] **Per-feature Model Override** — pilih model berbeda untuk tiap fitur langsung dari meta box (misal: pakai Gemini Flash untuk meta, pakai GPT-4o untuk artikel utama)
- [ ] **Groq sebagai provider kedua** — tambah `class-groq-api.php` dengan interface yang sama (`send_prompt()`), karena Groq API gratis dan paling cepat untuk Llama 3.3 70B

---

## 🔗 Fase 3 — Integrasi Ekosistem WordPress (Target: v2.0)

> Memanfaatkan fitur baru **WordPress 6.9 "Gene"** dan AI Building Blocks ecosystem.

### 3.1 Abilities API *(WP 6.9 — sudah tersedia)*

WordPress 6.9 memperkenalkan **Abilities API**: first-class cross-context API untuk mendaftarkan kemampuan plugin dalam format yang machine-readable dan dapat diakses dari PHP, JS, maupun REST API.

**Rencana implementasi:**

Refactor semua custom AJAX handler menjadi registered WordPress Abilities:

```php
// Contoh: ganti handle_ajax_request() dengan Ability
wp_register_ability( 'ai-content-master/generate-article', [
    'label'               => 'Generate SGE Article',
    'description'         => 'Generate a full SGE-optimized article from a topic.',
    'input_schema'        => [
        'topic' => [ 'type' => 'string', 'required' => true ],
    ],
    'execute_callback'    => fn( $args ) => ( new AI_Content_Master_Article_Generator() )
                                ->generate_article( $args['topic'] ),
    'permission_callback' => fn() => current_user_can( 'edit_posts' ),
] );
```

Manfaat:
- Handler lebih terstruktur, discoverable, dan aman
- Otomatis terintegrasi dengan WordPress REST API
- Bisa dipanggil dari konteks PHP, JS, dan eksternal tools

Abilities yang akan didaftarkan:
- `ai-content-master/generate-article`
- `ai-content-master/rewrite-article`
- `ai-content-master/analyze-seo`
- `ai-content-master/generate-meta`
- `ai-content-master/rephrase-text`

### 3.2 Block Processor API *(WP 6.9 — sudah tersedia)*

WordPress 6.9 memperkenalkan **Block Processor** baru yang bisa memindai dan memanipulasi struktur blok secara lazy dan streaming — mencegah out-of-memory crash pada konten panjang.

**Rencana implementasi:**

Ganti `wp_strip_all_tags(strip_shortcodes($post->post_content))` yang saat ini dipakai di Article Rewriter dan SEO Analyzer dengan Block Processor:

```php
// Sebelumnya (tidak efisien untuk post panjang):
$content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );

// Setelah (streaming, memory-efficient):
$processor = new WP_Block_Parser_Block( $post->post_content );
$text_content = $processor->get_inner_text(); // lazy parse
```

Manfaat: lebih performa untuk artikel panjang, tidak ada risiko memory exhaustion.

### 3.3 Integrasi Yoast SEO / Rank Math

- [ ] Auto-fill meta description ke field Yoast/Rank Math setelah generate
- [ ] Baca focus keyword dari Yoast untuk menyertakannya dalam prompt SEO analysis

---

## 🤖 Fase 4 — AI-Native WordPress (Target: v3.0)

> Memanfaatkan **WP AI Client SDK** dan **MCP Adapter** yang sedang menuju WP Core 7.0.

### 4.1 Migrasi ke WP AI Client SDK *(Menuju WP Core 7.0)*

WordPress sedang mengembangkan **WP AI Client** — wrapper resmi yang menyediakan antarmuka terpadu untuk semua provider AI:

```php
// Visi: ganti seluruh class-openrouter-api.php dengan:
$text = AI_Client::prompt( 'Write a poem about Flores.' )->generate_text();
```

**Rencana implementasi:**
- Pertahankan `class-openrouter-api.php` sebagai adapter fallback
- Buat `class-wp-ai-client-adapter.php` yang implement interface yang sama
- Switch otomatis berdasarkan availability WP AI Client di environment

Manfaat:
- Credential management terpusat di WordPress (tidak perlu simpan API key sendiri)
- Support multi-provider native (OpenAI, Gemini, Anthropic, Groq, dll.)
- Ikut roadmap WordPress core — plugin lebih future-proof

### 4.2 MCP Adapter — Plugin sebagai AI Tools *(WP 6.9+ official package)*

**MCP Adapter** adalah official WordPress package yang mengadaptasi Abilities yang sudah terdaftar menjadi primitif yang didukung **Model Context Protocol (MCP)**.

Artinya: setelah Fase 3 (Abilities API) selesai, **plugin ini bisa dikendalikan langsung dari:**
- Claude Desktop
- Cursor / VS Code Copilot
- ChatGPT (lewat MCP connector)
- GitHub Copilot
- Tool AI manapun yang support MCP

**Contoh use case setelah MCP terimplementasi:**

> User di Claude Desktop: *"Tolong generate artikel tentang cara budidaya kopi Flores untuk website WordPress saya."*  
> → Claude memanggil Ability `ai-content-master/generate-article` lewat MCP  
> → Artikel langsung dibuat di WordPress — tanpa buka browser, tanpa buka WP Admin

**Rencana implementasi:**
- Install `wordpress/mcp-adapter` package
- Pastikan semua Ability sudah terdaftar dengan `input_schema` yang lengkap
- Tambah autentikasi MCP via WordPress Application Passwords

---

## 📊 Ringkasan Timeline

```
v1.1  ✅  Sekarang    Bug fixes + Smart Model Selector
v1.2  🚧  ~1 bulan   Streaming response + UX improvements
v1.3  📋  ~2-3 bulan Content history + Bulk generator + Groq provider
v2.0  🔗  ~4-6 bulan Abilities API + Block Processor + Yoast/RankMath integrasi
v3.0  🤖  ~8-12 bulan WP AI Client SDK + MCP Adapter (AI agent control)
```

---

## 📚 Referensi

| Topik | Link |
|-------|------|
| WordPress 6.9 Release Notes | https://wordpress.org/news/category/releases/ |
| Abilities API Documentation | https://developer.wordpress.org |
| WP AI Client (PHP AI Client) | https://github.com/WordPress/ai-services |
| MCP Adapter for WordPress | https://github.com/WordPress/wordpress-develop |
| OpenRouter API Docs | https://openrouter.ai/docs |
| Groq API Docs | https://console.groq.com/docs |
| Model Context Protocol | https://modelcontextprotocol.io |

---

> *"The best time to plant a tree was 20 years ago. The second best time is now."*  
> — Satu plugin kecil hari ini, bisa jadi AI agent gateway besok. 🌱
