/* Zayan Hardware PWA service worker.
 * Cache public static assets only. Never cache PHP/HTML pages, account data,
 * POST requests or user uploads.
 */
'use strict';
const CACHE_PREFIX='zayan-hardware-static-';
const STATIC_CACHE=CACHE_PREFIX+'v4';
const OFFLINE_URL=new URL('./offline.html',self.registration.scope).href;
const STATIC_PATH=/\/(?:css|js|assets)\//;
const STATIC_FILE=/\.(?:css|js|svg|png|jpg|jpeg|webp|woff2?)$/i;
self.addEventListener('install',(event)=>{event.waitUntil((async()=>{const cache=await caches.open(STATIC_CACHE);try{await cache.add(new Request(OFFLINE_URL,{cache:'reload'}));}catch(_){}await self.skipWaiting();})());});
self.addEventListener('activate',(event)=>{event.waitUntil((async()=>{const keys=await caches.keys();await Promise.all(keys.filter((key)=>key.startsWith(CACHE_PREFIX)&&key!==STATIC_CACHE).map((key)=>caches.delete(key)));await self.clients.claim();})());});
self.addEventListener('fetch',(event)=>{const request=event.request;if(request.method!=='GET')return;const url=new URL(request.url);if(url.origin!==self.location.origin)return;
if(request.mode==='navigate'){event.respondWith((async()=>{try{return await fetch(request);}catch(_){const cache=await caches.open(STATIC_CACHE);return(await cache.match(OFFLINE_URL))||new Response('أنت غير متصل بالإنترنت. اتصل بالشبكة ثم حاول مجددًا.',{status:503,headers:{'Content-Type':'text/plain; charset=utf-8'}});}})());return;}
if(!STATIC_PATH.test(url.pathname)||!STATIC_FILE.test(url.pathname))return;
event.respondWith((async()=>{const cache=await caches.open(STATIC_CACHE);const cached=await cache.match(request);if(cached)return cached;try{const response=await fetch(request);if(response&&response.ok&&response.type==='basic')await cache.put(request,response.clone());return response;}catch(_){return new Response('',{status:504,statusText:'Offline'});}})());});

