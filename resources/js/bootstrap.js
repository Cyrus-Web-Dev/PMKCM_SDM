import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Kirim alamat halaman aktif di setiap permintaan XHR/Inertia. Server memakainya supaya setelah tambah/ubah/hapus data
// halaman kembali ke halaman yang sama (bukan ke dashboard) walau header Referer hilang di hosting/proxy.
window.axios.interceptors.request.use((config) => {
    config.headers['X-Current-Url'] = window.location.pathname + window.location.search;
    return config;
});
