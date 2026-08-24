const fs=require('fs');
const html=fs.readFileSync(process.env.LOCALAPPDATA+'/Temp/p8.html','utf8');
// temukan posisi error: potong script dan coba parse per bagian
const m=html.match(/<script>([\s\S]*)<\/script>/);
const code=m[1];
const lines=code.split('\n');
let lo=1, hi=lines.length;
function ok(n){try{new Function(lines.slice(0,n).join('\n'));return true}catch(e){return false}}
// binary search batas pertama yang gagal
while(lo<hi){const mid=Math.floor((lo+hi)/2);if(ok(mid))lo=mid+1;else hi=mid;}
console.log('Gagal sekitar baris', lo);
console.log('Konteks:', lines.slice(Math.max(0,lo-4),lo+2).map((l,i)=>(lo-3+i)+': '+l).join('\n'));
