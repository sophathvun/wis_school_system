<?php
namespace App\Support;

/** Conservative, local recognition of a single capital A in the fixed absence box. */
class ScholarshipAbsenceMark
{
    public const BOX=[545,253,48,42];

    public static function box(int $layoutVersion): array
    {return $layoutVersion===5?[545,263,48,42]:self::BOX;}

    public function read(\GdImage $image,int $layoutVersion=ScholarshipAnswerSheetLayout::VERSION): array
    {
        [$bx,$by,$bw,$bh]=self::box($layoutVersion);$pixels=[];$light=[];
        for($y=$by+5;$y<$by+$bh-5;$y++)for($x=$bx+5;$x<$bx+$bw-5;$x++){
            $rgb=imagecolorat($image,$x,$y);$value=.2126*(($rgb>>16)&255)+.7152*(($rgb>>8)&255)+.0722*($rgb&255);
            $pixels[]=[$x,$y,$value];$light[]=$value;
        }
        sort($light);$paper=$light[(int)(count($light)*.9)];$threshold=$paper-max(26,$paper*.12);
        $ink=array_values(array_filter($pixels,fn($p)=>$p[2]<$threshold));
        if(count($ink)<7)return ['state'=>'blank','confidence'=>1.0];
        $left=min(array_column($ink,0));$right=max(array_column($ink,0));$top=min(array_column($ink,1));$bottom=max(array_column($ink,1));
        $width=$right-$left;$height=$bottom-$top;$density=count($ink)/max(1,($width+1)*($height+1));
        if($width<8||$height<12||$width/$height<.32||$width/$height>1.55||$density>.60)return ['state'=>'unclear','confidence'=>0.0];
        $points=array_map(fn($p)=>[$p[0]-$left,$p[1]-$top],$ink);$mask=[];
        foreach($points as [$x,$y])$mask[$y.':'.$x]=true;
        $near=function($x,$y,$tolerance)use($mask){
            for($dy=-(int)ceil($tolerance);$dy<=ceil($tolerance);$dy++)for($dx=-(int)ceil($tolerance);$dx<=ceil($tolerance);$dx++)
                if($dx*$dx+$dy*$dy<=$tolerance*$tolerance&&isset($mask[((int)round($y)+$dy).':'.((int)round($x)+$dx)]))return true;
            return false;
        };
        $distance=function($x,$y,$line){
            [$x1,$y1,$x2,$y2]=$line;$dx=$x2-$x1;$dy=$y2-$y1;$t=max(0,min(1,(($x-$x1)*$dx+($y-$y1)*$dy)/max(.001,$dx*$dx+$dy*$dy)));
            return hypot($x-($x1+$t*$dx),$y-($y1+$t*$dy));
        };
        $tolerance=max(2.2,min(3.2,$width*.085));$best=0;
        foreach([.30,.40,.50,.60,.70] as $apex)foreach([.45,.55,.65,.75] as $bar)foreach([-.08,0,.08] as $slant){
            $ax=$apex*$width;$barLeft=$ax*(1-$bar);$barRight=$ax+($width-$ax)*$bar;
            $lines=[[$ax,0,0,$height],[$ax,0,$width,$height],[$barLeft,$bar*$height,$barRight,($bar+$slant)*$height]];
            $matched=0;foreach($points as [$x,$y])if(min(array_map(fn($line)=>$distance($x,$y,$line),$lines))<=$tolerance)$matched++;
            $coverage=[];foreach($lines as [$x1,$y1,$x2,$y2]){
                $found=0;for($i=0;$i<=20;$i++)if($near($x1+($x2-$x1)*$i/20,$y1+($y2-$y1)*$i/20,$tolerance))$found++;
                $coverage[]=$found/21;
            }
            $precision=$matched/count($points);$score=$precision*.5+$coverage[0]*.2+$coverage[1]*.2+$coverage[2]*.1;
            // Both legs and the crossbar are necessary; checks, crosses and filled boxes are not A.
            if($precision>=.85&&min($coverage[0],$coverage[1])>=.76&&$coverage[2]>=.76)$best=max($best,$score);
        }
        return ['state'=>$best>=.89?'absent':'unclear','confidence'=>round($best,3)];
    }
}
