export const sheetSize = { width: 1000, height: 1414 };
export const sheetMarkers = [[35,35],[965,35],[965,1379],[35,1379]];

export function perspectiveTransform(from, to) {
    if (from.length !== 4 || to.length !== 4) throw new Error('Select all four corner squares.');
    const matrix=[];
    for(let i=0;i<4;i++){
        const [u,v]=from[i], [x,y]=to[i];
        matrix.push([u,v,1,0,0,0,-x*u,-x*v,x],[0,0,0,u,v,1,-y*u,-y*v,y]);
    }
    for(let i=0;i<8;i++){
        let pivot=i;for(let r=i+1;r<8;r++)if(Math.abs(matrix[r][i])>Math.abs(matrix[pivot][i]))pivot=r;
        [matrix[i],matrix[pivot]]=[matrix[pivot],matrix[i]];
        if(Math.abs(matrix[i][i])<1e-9)throw new Error('The corners overlap. Select the four marker centers again.');
        const divisor=matrix[i][i];for(let c=i;c<9;c++)matrix[i][c]/=divisor;
        for(let r=0;r<8;r++)if(r!==i){const amount=matrix[r][i];for(let c=i;c<9;c++)matrix[r][c]-=amount*matrix[i][c];}
    }
    const h=matrix.map(row=>row[8]);
    return (x,y)=>{const d=h[6]*x+h[7]*y+1;return [(h[0]*x+h[1]*y+h[2])/d,(h[3]*x+h[4]*y+h[5])/d];};
}
export function validCorners(points,width,height) {
    if(points.length!==4)return false;
    const crosses=points.map(([x,y],i)=>{const b=points[(i+1)%4],c=points[(i+2)%4];return (b[0]-x)*(c[1]-b[1])-(b[1]-y)*(c[0]-b[0]);});
    const area=Math.abs(points.reduce((sum,p,i)=>sum+p[0]*points[(i+1)%4][1]-p[1]*points[(i+1)%4][0],0))/2;
    return crosses.every(n=>n>0)&&area>width*height*.08&&points.every(([x,y])=>x>=0&&y>=0&&x<width&&y<height);
}
/** Connected solid squares near the page's four corners, not text or QR modules. */
export function findSheetCorners(pixels,width,height) {
    const seen=new Uint8Array(width*height), components=[];
    const dark=i=>pixels[i*4]*.2126+pixels[i*4+1]*.7152+pixels[i*4+2]*.0722<85;
    const min=Math.max(4,Math.min(width,height)*.006),max=Math.min(width,height)*.065;
    for(let start=0;start<seen.length;start++){
        if(seen[start]||!dark(start))continue;
        const queue=[start];seen[start]=1;let left=width,right=0,top=height,bottom=0;
        for(let n=0;n<queue.length;n++){
            const i=queue[n],x=i%width,y=Math.floor(i/width);left=Math.min(left,x);right=Math.max(right,x);top=Math.min(top,y);bottom=Math.max(bottom,y);
            for(const next of [x?i-1:-1,x+1<width?i+1:-1,y?i-width:-1,y+1<height?i+width:-1])if(next>=0&&!seen[next]&&dark(next)){seen[next]=1;queue.push(next);}
        }
        const w=right-left+1,h=bottom-top+1;
        if(w>=min&&h>=min&&w<max&&h<max&&w/h>.6&&w/h<1.65&&queue.length/(w*h)>.65)components.push([(left+right)/2,(top+bottom)/2]);
    }
    const targets=[[0,0],[width,0],[width,height],[0,height]];
    const points=targets.map(([x,y])=>components.filter(([cx,cy])=>(x?cx>width/2:cx<width/2)&&(y?cy>height/2:cy<height/2))
        .sort((a,b)=>(a[0]-x)**2+(a[1]-y)**2-((b[0]-x)**2+(b[1]-y)**2))[0]);
    return points.every(Boolean)&&validCorners(points,width,height)?points:[];
}
export function alignSheet(source,points) {
    if(!validCorners(points,source.width,source.height))throw new Error('Tap the four marker centers clockwise, starting at the top left.');
    const map=perspectiveTransform(sheetMarkers,points);
    const input=source.getContext('2d').getImageData(0,0,source.width,source.height).data;
    const canvas=document.createElement('canvas');canvas.width=sheetSize.width;canvas.height=sheetSize.height;
    const ctx=canvas.getContext('2d'),output=ctx.createImageData(canvas.width,canvas.height),data=output.data;
    for(let y=0;y<canvas.height;y++)for(let x=0;x<canvas.width;x++){
        const [sx,sy]=map(x,y),i=(y*canvas.width+x)*4,ix=Math.floor(sx),iy=Math.floor(sy);data[i+3]=255;
        if(ix<0||iy<0||ix+1>=source.width||iy+1>=source.height){data[i]=data[i+1]=data[i+2]=255;continue;}
        const fx=sx-ix,fy=sy-iy;
        for(let c=0;c<3;c++)data[i+c]=input[(iy*source.width+ix)*4+c]*(1-fx)*(1-fy)+input[(iy*source.width+ix+1)*4+c]*fx*(1-fy)
            +input[((iy+1)*source.width+ix)*4+c]*(1-fx)*fy+input[((iy+1)*source.width+ix+1)*4+c]*fx*fy;
    }
    ctx.putImageData(output,0,0);return canvas;
}
