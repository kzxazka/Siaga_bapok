// File: public/js/auto-refresh.js
document.addEventListener('DOMContentLoaded', function() {
    // Auto refresh setiap 5 menit
    const refreshInterval = 5 * 60 * 1000; // 5 menit
    let lastUpdate = new Date();
    
    function updateLastUpdate() {
        const now = new Date();
        const options = { 
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit',
            hour12: false
        };
        document.getElementById('last-update').textContent = 
            `Terakhir diperbarui: ${now.toLocaleTimeString('id-ID', options)}`;
    }
    
    // Update waktu terakhir
    updateLastUpdate();
    
    // Auto refresh
    setInterval(function() {
        if (window.location.pathname === '/harga' || 
            window.location.pathname === '/harga/') {
            window.location.reload();
        } else {
            updateLastUpdate();
        }
    }, refreshInterval);
    
    // Countdown
    setInterval(function() {
        const now = new Date();
        const nextUpdate = new Date(lastUpdate.getTime() + refreshInterval);
        const diff = Math.floor((nextUpdate - now) / 1000); // dalam detik
        
        if (diff > 0) {
            const minutes = Math.floor(diff / 60);
            const seconds = diff % 60;
            document.getElementById('countdown').textContent = 
                `Pembaruan berikutnya dalam: ${minutes}m ${seconds}s`;
        }
    }, 1000);
});