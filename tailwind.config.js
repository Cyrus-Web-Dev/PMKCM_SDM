import forms from '@tailwindcss/forms'

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{vue,js}',
    ],
    theme: {
        extend: {
            fontFamily: {
                // Tema PMKCM: judul memakai Oswald (kapital, renggang), isi memakai IBM Plex Sans.
                serif: ['Oswald', 'Impact', '"Arial Narrow"', 'sans-serif'],
                sans: ['"IBM Plex Sans"', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'Arial', 'sans-serif'],
                mono: ['"IBM Plex Mono"', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
            },
            // Sudut tegas (kotak) seperti template PMKCM; rounded-full (avatar) tetap bulat.
            borderRadius: { none: '0', sm: '0', DEFAULT: '0', md: '0', lg: '0', xl: '0', '2xl': '0' },
            colors: {
                // "Ink" - abu industri netral (teks, garis, panel). 900=panel gelap, 950=latar gelap.
                ink: {
                    50: '#f3f4f6', 100: '#e6e8eb', 200: '#d3d7dc', 300: '#b4bbc4',
                    400: '#8c96a2', 500: '#5d6672', 600: '#48515c', 700: '#343c45',
                    800: '#272e35', 900: '#1d2329', 950: '#14181d',
                },
                // "Seal" - oranye PMKCM (aksen utama: tombol, menu aktif, garis kartu).
                seal: {
                    50: '#fff3eb', 100: '#ffe2d0', 200: '#ffc3a0', 300: '#ff9d66',
                    400: '#f26b1d', 500: '#e0590f', 600: '#bf480a', 700: '#993b0a',
                    800: '#7a300c', 900: '#62290d',
                },
                paper: '#eceef0',
            },
            boxShadow: {
                card: 'none',
            },
        },
    },
    plugins: [forms],
}
