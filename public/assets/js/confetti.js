/*!
   ███████╗ █████╗      ██╗ █████╗ ██████╗
   ██╔════╝██╔══██╗     ██║██╔══██╗██╔══██╗
   █████╗  ███████║     ██║███████║██████╔╝
   ██╔══╝  ██╔══██║██   ██║██╔══██║██╔══██╗
   ██║     ██║  ██║╚█████╔╝██║  ██║██║  ██║
   ╚═╝     ╚═╝  ╚═╝ ╚════╝ ╚═╝  ╚═╝╚═╝  ╚═╝

╭┌─┐╮  ╔═╗╔╦╗╔═╗  ╗  ╔╦╗╔═╗╦ ╦╔═╗  ╔═╗╔═╗╔═╗╔═╗
││  │  ╚═╗║║║╠═╝  ║   ║║╠═╣║║║║╣   ╔═╝║ ║╔═╝╠═╗
╰└─┘╯  ╚═╝╩ ╩╩    ╩  ═╩╝╩ ╩╚╩╝╚═╝  ╚═╝╚═╝╚═╝╚═╝
*/
(function(){'use strict';var canvas=document.querySelector('[data-confetti]');if(!canvas){return}
var storageKey='osis2026.confetti.'+(canvas.getAttribute('data-confetti-key')||'final');var reduced=!!(window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches);function remove(){if(canvas.parentNode){canvas.parentNode.removeChild(canvas)}}
function alreadyShown(){try{return window.sessionStorage.getItem(storageKey)==='1'}catch(e){return!1}}
function markShown(){try{window.sessionStorage.setItem(storageKey,'1')}catch(e){}}
var ctx=canvas.getContext?canvas.getContext('2d'):null;if(reduced||!ctx||!window.requestAnimationFrame||alreadyShown()){remove();return}
var palette=(canvas.getAttribute('data-confetti-colors')||'').split(',').filter(function(color){return/^#[0-9a-f]{6}$/i.test(color)});var colors=palette.length>0?[palette[0],palette[0],palette[0]].concat(palette):[];colors.push('#15141A','#15141A','#8D897D','#E3E0D8');var DURATION=5200;var FADE=900;var GRAVITY=900;var width=0;var height=0;var dpr=1;var pieces=[];var volleys=[];var started=0;var last=0;var rafId=null;var done=!1;function rand(min,max){return min+Math.random()*(max-min)}
function resize(){dpr=Math.min(window.devicePixelRatio||1,2);width=window.innerWidth;height=window.innerHeight;canvas.width=Math.round(width*dpr);canvas.height=Math.round(height*dpr)}
function budget(){var nav=window.navigator;var weak=(nav.deviceMemory&&nav.deviceMemory<=2)||(nav.hardwareConcurrency&&nav.hardwareConcurrency<=2)||(nav.connection&&nav.connection.saveData===!0);return Math.max(weak?60:90,Math.min(weak?80:200,Math.round(width*height/4500)))}
function makePiece(x,y,angle,speed){var kind=Math.random();return{x:x,y:y,vx:Math.cos(angle)*speed,vy:Math.sin(angle)*speed,w:rand(7,12),h:kind<0.55?rand(11,17):rand(4,7),round:kind>0.9,rot:rand(0,Math.PI*2),spin:rand(-8,8),flip:rand(0,Math.PI*2),flipSpeed:rand(5,11),drag:rand(0.9,1.6),fall:rand(110,220),sway:rand(16,40),color:colors[Math.floor(Math.random()*colors.length)]}}
function burst(count){for(var i=0;i<count;i++){var fromLeft=i%2===0;var angle=(fromLeft?-0.3:-0.7)*Math.PI+rand(-0.26,0.26);var speed=rand(0.8,1.15)*2*height;pieces.push(makePiece(fromLeft?width*0.03:width*0.97,height+12,angle,speed))}}
function rain(count){for(var i=0;i<count;i++){pieces.push(makePiece(rand(0,width),rand(-140,-12),Math.PI/2,rand(40,120)))}}
function step(dt){for(var i=pieces.length-1;i>=0;i--){var p=pieces[i];var damp=Math.exp(-p.drag*dt);p.vy+=GRAVITY*dt;p.vx*=damp;p.vy=p.vy>0?Math.min(p.vy,p.fall):p.vy*damp;p.flip+=p.flipSpeed*dt;p.rot+=p.spin*dt;p.x+=(p.vx+Math.sin(p.flip)*p.sway)*dt;p.y+=p.vy*dt;if(p.y>height+40&&p.vy>0){pieces.splice(i,1)}}}
function draw(alpha){ctx.setTransform(1,0,0,1,0,0);ctx.clearRect(0,0,canvas.width,canvas.height);ctx.globalAlpha=alpha;for(var i=0;i<pieces.length;i++){var p=pieces[i];var cos=Math.cos(p.rot);var sin=Math.sin(p.rot);var squash=Math.cos(p.flip);ctx.setTransform(dpr*cos,dpr*sin,-dpr*sin*squash,dpr*cos*squash,dpr*p.x,dpr*p.y);ctx.fillStyle=p.color;if(p.round){ctx.beginPath();ctx.arc(0,0,p.w/2,0,Math.PI*2);ctx.fill()}else{ctx.fillRect(-p.w/2,-p.h/2,p.w,p.h)}}}
function stop(){if(done){return}
done=!0;if(rafId!==null){window.cancelAnimationFrame(rafId);rafId=null}
window.removeEventListener('resize',resize);document.removeEventListener('visibilitychange',onVisibility);remove()}
function frame(time){rafId=null;var elapsed=time-started;var dt=Math.min(0.05,Math.max(0.001,(time-last)/1000));last=time;while(volleys.length>0&&elapsed>=volleys[0].at){volleys.shift().run()}
step(dt);var remaining=DURATION-elapsed;if(remaining<=0||(volleys.length===0&&pieces.length===0)){stop();return}
draw(remaining<FADE?remaining/FADE:1);rafId=window.requestAnimationFrame(frame)}
function onVisibility(){if(document.hidden&&started>0){stop()}}
function start(){if(started>0||done||document.hidden){return}
resize();var total=budget();volleys=[{at:0,run:function(){burst(Math.round(total*0.45))}},{at:250,run:function(){burst(Math.round(total*0.25))}},{at:550,run:function(){rain(Math.round(total*0.3))}}];markShown();canvas.hidden=!1;window.addEventListener('resize',resize);started=window.performance&&window.performance.now?window.performance.now():Date.now();last=started;rafId=window.requestAnimationFrame(function(time){started=time;last=time;frame(time)})}
document.addEventListener('visibilitychange',function(){if(!document.hidden&&started===0&&!done){window.setTimeout(start,350)}});document.addEventListener('visibilitychange',onVisibility);if(!document.hidden){window.setTimeout(start,350)}})()