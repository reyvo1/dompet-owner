const fs=require('fs');
const html=fs.readFileSync(process.env.LOCALAPPDATA+'/Temp/p8.html','utf8');
const idx=html.lastIndexOf('<script>');
const code=html.slice(idx+8, html.lastIndexOf('</script>'));
try{new Function(code);console.log('JS SYNTAX OK');}catch(e){
 const lines=code.split('\n');
 const mm=e.stack.match(/<anonymous>:(\d+)/);
 if(mm){const n=+mm[1];
  console.log('baris ~'+n+':');
  for(let i=Math.max(0,n-3);i<Math.min(lines.length,n+2);i++)console.log((i+1)+': '+lines[i].slice(0,160));
 } else console.log(e.stack.split('\n').slice(0,4).join('\n'));
}
