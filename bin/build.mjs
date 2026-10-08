import fs from 'node:fs';import path from 'node:path';import {execFileSync} from 'node:child_process';
const root=process.cwd(),target=path.join(root,'public/assets');fs.mkdirSync(target+'/fonts',{recursive:true});
function copyFont(pkg,needle,out){let files=fs.readdirSync('node_modules/@fontsource/'+pkg+'/files'),name=files.find(x=>x===needle);if(!name)throw Error('Font missing: '+needle);fs.copyFileSync('node_modules/@fontsource/'+pkg+'/files/'+name,target+'/fonts/'+out)}
// Bundle the exact weights used by the supplied overview.
copyFont('inter','inter-latin-400-normal.woff2','inter-latin.woff2');copyFont('inter','inter-latin-500-normal.woff2','inter-medium.woff2');copyFont('jetbrains-mono','jetbrains-mono-latin-500-normal.woff2','mono-medium.woff2');copyFont('inter','inter-latin-600-normal.woff2','inter-semibold.woff2');copyFont('inter','inter-latin-700-normal.woff2','inter-bold.woff2');copyFont('jetbrains-mono','jetbrains-mono-latin-400-normal.woff2','mono-latin.woff2');copyFont('noto-serif-bengali','noto-serif-bengali-bengali-400-normal.woff2','bengali.woff2');
const cm='node_modules/codemirror';let parts=[cm+'/lib/codemirror.js',...['clike','javascript','python','xml','css','htmlmixed','sql','php'].map(m=>`${cm}/mode/${m}/${m}.js`),cm+'/addon/edit/matchbrackets.js',cm+'/addon/display/fullscreen.js'];fs.writeFileSync(target+'/editor.js',parts.map(x=>fs.readFileSync(x,'utf8')).join('\n;\n'));fs.writeFileSync(target+'/editor.css',fs.readFileSync(cm+'/lib/codemirror.css','utf8')+'\n'+fs.readFileSync(cm+'/addon/display/fullscreen.css','utf8'));
// Compile the source reference with its original Tailwind 3 design tokens.
const base=fs.readFileSync('frontend/app.css','utf8').replace(/^@(import|source).*$/gm,'');
fs.writeFileSync(target+'/app.css',base);
execFileSync(process.execPath,['node_modules/tailwind-reference/lib/cli.js','-c','frontend/reference.tailwind.cjs','-i','frontend/reference.css','-o','public/assets/reference.css','--minify'],{stdio:'inherit'});
console.log('Production assets built. No Node server required.');

execFileSync('node_modules/.bin/esbuild',['node_modules/qrcode/lib/browser.js','--bundle','--format=esm','--minify','--outfile=public/assets/qr.js'],{stdio:'inherit'});


// Minified, content-addressed entry and lazy chunks. Keep old chunks for open tabs.
const {build}=await import('esbuild');
const built=await build({entryPoints:['public/assets/app.js'],bundle:true,format:'esm',splitting:true,minify:true,outdir:'public/assets/dist',entryNames:'app-[hash]',chunkNames:'chunk-[hash]',metafile:true,plugins:[{name:'preserve-file-preview-paths',setup(builder){builder.onResolve({filter:/^\.\/file-previews\.js/},args=>({path:'../'+args.path.slice(2),external:true}));}}]});
const meta=built.metafile;
const entry=Object.entries(meta.outputs).find(([,v])=>v.entryPoint==='public/assets/app.js')?.[0];
if(!entry)throw Error('Built application entry missing.');
const {createHash}=await import('node:crypto'),sources={};
for(const input of Object.keys(meta.inputs)){const source=input.split('?')[0];if(fs.existsSync(source))sources[source]=createHash('sha256').update(fs.readFileSync(source)).digest('hex');}
fs.writeFileSync(target+'/dist/manifest.json.next',JSON.stringify({entry:path.relative('public',entry).replaceAll('\\','/'),sources}));
fs.renameSync(target+'/dist/manifest.json.next',target+'/dist/manifest.json');

