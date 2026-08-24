const fs=require('fs');
const html=fs.readFileSync(process.env.LOCALAPPDATA+'/Temp/p8.html','utf8');
// regex <script> menangkap <script src=...> pertama; ambil script inline terakhir
const idx=html.lastIndexOf('<script>');
const code=html.slice(idx+8, html.lastIndexOf('</script>'));
try{new Function(code);console.log('JS SYNTAX OK');}catch(e){console.log('SYNTAX ERR:',e.message);
 // cari baris
 const lines=code.split('\n');
 const mm=e.stack.match(/<anonymous>:(\d+)/);
 if(mm)console.log('baris ~',mm[1],':',lines[+mm[1]-2]);
}
