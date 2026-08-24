#!/bin/bash
# =============================================================
# BACKUP OTOMATIS DATABASE DOMPET OWNER
# Simpan 14 backup terakhir di dompet-owner/backups/
# Jalankan manual: bash tools/backup-db.sh
# =============================================================
set -u
PROJ="/c/Users/IVO/dompet-owner"
DUMP="/c/Program Files/MySQL/MySQL Server 8.4/bin/mysqldump.exe"
DEST="$PROJ/backups"
KEEP=14

mkdir -p "$DEST"
STAMP=$(date +%Y%m%d_%H%M)
OUT="$DEST/dompet_owner_$STAMP.sql"

"$DUMP" --host=127.0.0.1 --port=3308 --user=root --password="Keuanganku2026!" \
  --single-transaction --routines --triggers --default-character-set=utf8mb4 \
  dompet_owner > "$OUT" 2>/dev/null

if [ ! -s "$OUT" ]; then
  echo "GAGAL: dump kosong -> $OUT"
  rm -f "$OUT"
  exit 1
fi

# hapus backup lama, sisakan $KEEP terakhir
ls -1t "$DEST"/dompet_owner_*.sql 2>/dev/null | tail -n +$((KEEP+1)) | while read -r f; do rm -f "$f"; done

SIZE=$(du -h "$OUT" | cut -f1)
echo "OK: $OUT ($SIZE)"
