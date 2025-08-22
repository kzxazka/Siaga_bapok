<?php
/**
 * AI Configuration
 * Siaga Bapok - Sistem Informasi Harga Bahan Pokok
 */

class AIConfig {
    // Google Gemini API Key
    private static $apiKey = 'AIzaSyAYHFvg12T9R6ENjnhsWqtcpUWOSRoQVKQ'; // Ganti dengan API key Anda
    private static $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent';
    
    /**
     * Get Gemini API Key
     */
    public static function getApiKey() {
        return self::$apiKey;
    }
    
    /**
     * Get Gemini API URL
     */
    public static function getApiUrl() {
        return self::$apiUrl;
    }
    
    /**
     * Send request to Gemini API
     * 
     * @param array $data Data to be sent to Gemini API
     * @return array|null Response from Gemini API or null on error
     */
    public static function sendRequest($data) {
        $url = self::$apiUrl . '?key=' . self::$apiKey;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification for testing
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Set timeout to 30 seconds
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($error) {
            error_log("Gemini API Error: " . $error);
            return null;
        }
        
        if ($httpCode != 200) {
            error_log("Gemini API HTTP Error: " . $httpCode . " Response: " . $response);
            return null;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Generate AI insight from price data
     * 
     * @param array $priceData Array of price data
     * @param string $period Period of analysis (weekly, monthly, 6months)
     * @return string|null AI generated insight or null on error
     */
    public static function generateInsight($priceData, $period) {
        // Prepare data for Gemini API
        $prompt = self::preparePrompt($priceData, $period);
        
        // Coba gunakan API Gemini
        try {
            $data = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'topK' => 40,
                    'topP' => 0.95,
                    'maxOutputTokens' => 1024,
                ]
            ];
            
            $response = self::sendRequest($data);
            
            if (!$response || !isset($response['candidates'][0]['content']['parts'][0]['text'])) {
                error_log("Failed to get valid response from Gemini API");
                // Gunakan fallback content jika API tidak tersedia
                return self::getFallbackInsight($priceData, $period);
            }
            
            return $response['candidates'][0]['content']['parts'][0]['text'];
        } catch (Exception $e) {
            error_log("Exception in generateInsight: " . $e->getMessage());
            return self::getFallbackInsight($priceData, $period);
        }
    }
    
    /**
     * Get fallback insight when API is not available
     * 
     * @param array $priceData Array of price data
     * @param string $period Period of analysis (weekly, monthly, 6months)
     * @return string Fallback insight
     */
    private static function getFallbackInsight($priceData, $period) {
        // Buat insight sederhana berdasarkan data yang tersedia
        $periodText = '';
        switch ($period) {
            case 'weekly':
                $periodText = '1 minggu terakhir';
                break;
            case 'monthly':
                $periodText = '1 bulan terakhir';
                break;
            case '6months':
                $periodText = '6 bulan terakhir';
                break;
            default:
                $periodText = '1 minggu terakhir';
        }
        
        // Hitung rata-rata harga per komoditas
        $commodityPrices = [];
        foreach ($priceData as $item) {
            $commodity = $item['commodity_name'];
            if (!isset($commodityPrices[$commodity])) {
                $commodityPrices[$commodity] = [
                    'prices' => [],
                    'markets' => []
                ];
            }
            $commodityPrices[$commodity]['prices'][] = $item['price'];
            if (!in_array($item['market_name'], $commodityPrices[$commodity]['markets'])) {
                $commodityPrices[$commodity]['markets'][] = $item['market_name'];
            }
        }
        
        $html = "<h4>Analisis Harga Komoditas $periodText</h4>";
        $html .= "<p>Berikut adalah ringkasan harga komoditas berdasarkan data yang tersedia:</p>";
        
        $html .= "<h5>Ringkasan Harga Komoditas:</h5>";
        $html .= "<ul>";
        foreach ($commodityPrices as $commodity => $data) {
            $avgPrice = array_sum($data['prices']) / count($data['prices']);
            $markets = implode(', ', $data['markets']);
            $html .= "<li><strong>$commodity</strong>: Rata-rata Rp " . number_format($avgPrice, 0, ',', '.') . " (Pasar: $markets)</li>";
        }
        $html .= "</ul>";
        
        $html .= "<h5>Rekomendasi:</h5>";
        $html .= "<ul>";
        $html .= "<li>Pantau stok komoditas dengan harga tertinggi untuk mencegah kelangkaan</li>";
        $html .= "<li>Koordinasi dengan pemasok untuk memastikan pasokan yang stabil</li>";
        $html .= "<li>Lakukan pemantauan harga secara berkala untuk mengidentifikasi tren</li>";
        $html .= "</ul>";
        
        $html .= "<p><em>Catatan: Analisis ini dibuat berdasarkan data yang tersedia. Untuk analisis lebih mendalam, silakan coba lagi nanti.</em></p>";
        
        return $html;
    }
    
    /**
     * Prepare prompt for Gemini API
     * 
     * @param array $priceData Array of price data
     * @param string $period Period of analysis (weekly, monthly, 6months)
     * @return string Prepared prompt
     */
    private static function preparePrompt($priceData, $period) {
        $periodText = '';
        switch ($period) {
            case 'weekly':
                $periodText = '1 minggu terakhir';
                break;
            case 'monthly':
                $periodText = '1 bulan terakhir';
                break;
            case '6months':
                $periodText = '6 bulan terakhir';
                break;
            default:
                $periodText = '1 minggu terakhir';
        }
        
        // Convert price data to string representation
        $priceDataText = "Data Harga Komoditas $periodText:\n";
        foreach ($priceData as $item) {
            $priceDataText .= "- {$item['commodity_name']} di {$item['market_name']}: Rp " . 
                             number_format($item['price'], 0, ',', '.') . 
                             " (tanggal {$item['created_at']})\n";
        }
        
        // Create the prompt
        $prompt = <<<EOT
Anda adalah asisten analisis data harga bahan pokok. Berdasarkan data berikut, berikan:
1. Ringkasan tren harga untuk setiap komoditas dalam $periodText
2. Identifikasi komoditas yang mengalami kenaikan harga signifikan
3. Rekomendasi tindakan yang perlu diambil (misalnya: pantau stok, koordinasi dengan pemasok, dll)

$priceDataText

Berikan analisis dalam format HTML yang rapi dengan judul, paragraf, dan daftar poin. Fokus pada insight yang berguna untuk pengambilan keputusan.
EOT;
        
        return $prompt;
    }
}
?>