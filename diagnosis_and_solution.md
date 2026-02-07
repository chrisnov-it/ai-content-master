# Diagnosis dan Solusi AJAX Error Plugin AI Content Master

## Masalah yang Teridentifikasi

Berdasarkan analisis kode dan error log, masalah utama adalah **HTTP 400 Bad Request** pada AJAX call ke `admin-ajax.php`. Ini menunjukkan ada masalah dengan struktur request atau validasi di sisi server.

## Perbandingan dengan Plugin Hostinger

### Plugin Hostinger (Yang Berfungsi):
1. **Nonce Verification**: Menggunakan `wp_verify_nonce()` dengan action yang spesifik
2. **Error Handling**: Comprehensive error handling dengan `wp_send_json_error()`
3. **Input Sanitization**: Menggunakan `sanitize_text_field()` dan `wp_unslash()`
4. **AJAX Registration**: Proper registration dengan `wp_ajax_` hooks

### Plugin Anda (Yang Bermasalah):
1. **Nonce Action Mismatch**: Kemungkinan ada ketidakcocokan antara nonce yang digenerate dan yang diverifikasi
2. **Missing wp_localize_script**: Belum ada kode untuk localize script
3. **AJAX Hook Registration**: Perlu dipastikan hook sudah terdaftar dengan benar

## Solusi yang Direkomendasikan

### 1. Perbaiki AJAX Handler (PHP)

```php
<?php
class AI_Content_Master_Article_Generator {
    
    public function init() {
        // Pastikan hook terdaftar untuk logged-in users
        add_action('wp_ajax_ai_content_master_generate_article', array($this, 'handle_ajax_request'));
        
        // Jika perlu support untuk non-logged users (biasanya tidak perlu)
        // add_action('wp_ajax_nopriv_ai_content_master_generate_article', array($this, 'handle_ajax_request'));
    }

    public function handle_ajax_request() {
        // Debug log untuk troubleshooting
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AI Content Master: AJAX request received');
            error_log('POST data: ' . print_r($_POST, true));
        }

        // Security check - PENTING: pastikan nonce action sama dengan yang di JavaScript
        if (!check_ajax_referer('ai_content_master_generate_action', 'security', false)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AI Content Master: Nonce verification failed');
            }
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-content-master')), 403);
            return;
        }

        // Capability check
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-content-master')), 403);
            return;
        }

        // Get and validate topic - PENTING: gunakan wp_unslash()
        $topic = isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : '';
        if (empty($topic)) {
            wp_send_json_error(array('message' => __('Please provide a topic for the article.', 'ai-content-master')), 400);
            return;
        }

        // Generate the article
        $generated_article = $this->generate_article($topic);

        if (is_wp_error($generated_article)) {
            wp_send_json_error(array('message' => $generated_article->get_error_message()), 500);
        } else {
            wp_send_json_success(array('generated_article' => $generated_article));
        }
    }
}
```

### 2. Tambahkan wp_localize_script (PHP)

Buat file baru atau tambahkan ke class admin Anda:

```php
<?php
class AI_Content_Master_Admin {
    
    public function enqueue_scripts($hook) {
        // Hanya load di halaman edit post/page
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }

        // Enqueue script
        wp_enqueue_script(
            'ai-content-master-admin',
            plugin_dir_url(__FILE__) . 'assets/js/admin.js',
            array('jquery'),
            '1.0.0',
            true
        );

        // Localize script - PENTING: pastikan nonce action sama
        wp_localize_script('ai-content-master-admin', 'aiContentMasterAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_content_master_generate_action'), // Harus sama dengan yang di PHP
            'post_id' => get_the_ID()
        ));
    }

    public function init() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
}
```

### 3. Perbaiki JavaScript

```javascript
$('#ai-content-master-generate-article-btn').on('click', function () {
    var buttonId = 'ai-content-master-generate-article-btn';
    var $status = $('#ai-content-master-generate-article-status');
    var topic = $('#ai-content-master-article-topic').val();

    if (!topic) {
        $status.css('color', 'red').text('Please enter a topic.');
        return;
    }

    // Pastikan aiContentMasterAjax sudah di-localize
    if (typeof aiContentMasterAjax === 'undefined') {
        console.error('aiContentMasterAjax is not defined');
        $status.css('color', 'red').text('Configuration error. Please refresh the page.');
        return;
    }

    if (!confirm('This will replace your entire article content with a newly generated article. Are you sure?')) {
        return;
    }

    showSpinner(buttonId);
    $status.css('color', 'inherit').text('Generating article... This may take a few moments.');

    $.ajax({
        url: aiContentMasterAjax.ajax_url,
        type: 'POST',
        data: {
            action: 'ai_content_master_generate_article',
            security: aiContentMasterAjax.nonce, // Pastikan ini ada
            topic: topic
        },
        success: function (response) {
            console.log('AJAX Success:', response); // Debug log
            if (response.success) {
                // Handle success...
                var generatedContent = response.data.generated_article;
                // ... rest of success handling
                $status.css('color', 'green').text('Article generated successfully!');
            } else {
                console.error('API Error:', response.data);
                $status.css('color', 'red').text('Error: ' + (response.data.message || 'Unknown error'));
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error('AJAX Error Details:', {
                status: jqXHR.status,
                statusText: jqXHR.statusText,
                responseText: jqXHR.responseText,
                textStatus: textStatus,
                errorThrown: errorThrown
            });
            $status.css('color', 'red').text('AJAX request failed: ' + textStatus + ' (' + jqXHR.status + ')');
        },
        complete: function () {
            hideSpinner(buttonId);
        }
    });
});
```

### 4. Debugging Steps

1. **Enable WordPress Debug**:
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

2. **Check Debug Log**: Lihat file `/wp-content/debug.log`

3. **Test Nonce Manually**:
```javascript
// Di browser console
console.log('Nonce:', aiContentMasterAjax.nonce);
console.log('AJAX URL:', aiContentMasterAjax.ajax_url);
```

4. **Test AJAX Endpoint Manually**:
```bash
curl -X POST "http://webdev.local/wp-admin/admin-ajax.php" \
  -d "action=ai_content_master_generate_article&security=YOUR_NONCE&topic=test"
```

## Kemungkinan Penyebab Error 400

1. **Nonce Mismatch**: Action nonce di JavaScript berbeda dengan di PHP
2. **Missing wp_localize_script**: Variable `aiContentMasterAjax` tidak terdefinisi
3. **Hook Not Registered**: AJAX hook tidak terdaftar dengan benar
4. **Character Encoding**: Masalah dengan karakter khusus dalam topic
5. **Server Configuration**: Mod_security atau firewall memblokir request

## Implementasi Pola Hostinger

Adopsi pola yang sama seperti plugin Hostinger:

1. **Consistent Error Handling**: Selalu gunakan `wp_send_json_error()` dan `wp_send_json_success()`
2. **Proper Input Sanitization**: Gunakan `wp_unslash()` dan `sanitize_text_field()`
3. **Comprehensive Logging**: Log semua step untuk debugging
4. **Timeout Handling**: Set timeout yang cukup untuk API calls
5. **Rate Limiting**: Implementasi rate limiting seperti Hostinger

Dengan mengikuti pola ini, plugin Anda akan memiliki struktur yang solid dan reliable seperti plugin Hostinger yang sudah berfungsi dengan baik.
