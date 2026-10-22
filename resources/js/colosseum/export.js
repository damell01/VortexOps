
import * as THREE from 'three';
import {buildStage,animateStage} from '/resources/js/colosseum/stage.js';
const scene=new THREE.Scene();
const camera=new THREE.PerspectiveCamera(39,1280/360,.1,80);
const renderer=new THREE.WebGLRenderer({antialias:true,preserveDrawingBuffer:true,powerPreference:'high-performance'});
renderer.setSize(1280,360);renderer.setPixelRatio(1);renderer.outputColorSpace=THREE.SRGBColorSpace;
renderer.toneMapping=THREE.ACESFilmicToneMapping;renderer.toneMappingExposure=1.12;
document.body.append(renderer.domElement);
const set=buildStage(scene,{lite:true});
camera.position.set(0,3.1,10.4);camera.lookAt(0,1.5,0);
window.renderExportFrame=(n)=>{animateStage(set,n/24);camera.position.x=Math.sin(n/24*1.6)*.22;camera.lookAt(0,1.5,0);renderer.render(scene,camera);return renderer.domElement.toDataURL('image/png');};
window.exportReady=true;
