# 💰 Dompet Owner

Super-app keuangan untuk pemilik usaha:
- **1 kas dompet owner** — semua pemasukan & pengeluaran (pribadi maupun usaha) langsung menggerakkan kas ini
- **Transaksi dobel-peran** — belanja owner bisa dicatat sebagai Pribadi atau sebagai pengeluaran cabang tertentu; uang tetap keluar dari dompet owner, tapi laporan per-cabang tetap terpisah
- **Kariawan input via Telegram** — tiap kariawan punya akun di cabangnya; input `masuk 50000 jual nasi goreng` langsung menambah kas owner + notif otomatis ke bos
- **Web dashboard modern** — ringkasan, grafik tren 6 bulan, perbandingan cabang, transaksi terbaru
- **AI assistant** — tanya "cabang mana paling untung?" langsung ke AI (Gemini)

## Teknologi
PHP 8.4 + MySQL 8.4 (port 3308) + Chart.js. Tanpa framework — gampang dipindah ke hosting.

## Struktur
```
dompet-owner/
├── schema.sql          # database + data awal
├── src/config.php      # koneksi DB, mesin transaksi/kas, laporan
├── src/ai.php          # asisten AI (Gemini)
├── api.php             # REST API dashboard
├── bot/telegram.php    # bot Telegram (polling / webhook)
├── public/index.php    # web dashboard
└── tests/              # tes inti, tes bot, runner API
```

## Setup
1. Import DB: `mysql -h127.0.0.1 -P3308 -uroot -p < schema.sql`
2. Edit `src/config.php`: isi `BOT_TOKEN` dari @BotFather
3. Isi chat id Telegram owner (untuk notif):
   `UPDATE owner_profile SET telegram_chat_id='<chat_id_kamu>' WHERE id=1;`
4. Jalankan web: `php -S 0.0.0.0:8089 -t public`
5. Jalankan bot: `php bot/telegram.php`
6. (Opsional) AI: set env `GEMINI_KEY` atau
   `INSERT INTO settings (k,v) VALUES ('gemini_key','KEY_KAMU');`

## Akun awal
| User | Password | Peran |
|---|---|---|
| rey | admin123 | Owner (web) |
| budi | kariawan123 | Kariawan Cabang Contoh 1 |

Kariawan verifikasi Telegram: kirim `/start <username> <password>` ke bot.

## Format bot kariawan
```
masuk 50000 jual nasi goreng
keluar 25000 beli beras
/hariini   -> laporan hari ini cabangnya
```

## Konsep penting (double-view ledger)
Setiap transaksi WAJIB mempengaruhi salah satu kas dompet owner.
- Tanpa cabang → scope **pribadi**
- Dengan cabang → scope **usaha**, masuk laporan cabang itu
Laporan owner pribadi dan laporan tiap cabang selalu bisa ditarik terpisah.

## Tes
```
php tests/test_core.php   # mesin kas & laporan
php tests/test_bot.php    # logika bot (mock Telegram)
```
