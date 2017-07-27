<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    public function index(Request $request)
    {
        $files = $request->input('files');
        $filexts = '';
        if ($handle = @opendir('/srv/candocs/' . $request->id . '/final')) {
            while (false != ($file = readdir($handle))) {
                if ($file != '.' && $file != '..') {
                    $path_parts = pathinfo($file);
                    $filexts = $path_parts['extension'];
                    $cmd1 = '';
                    $cmd2 = '';
                    $cmd = '';
                    if ($filexts == 'msg') {
                        $cmd1 = 'cd /srv/candocs/' . $request->id . '/final && msgconvert /srv/candocs/' . $request->id . '/final/' . $file;
                        $cmd2 = 'cd /srv/candocs/' . $request->id . '/final && java -jar /opt/emailconverter/emailconverter2.jar /srv/candocs/' . $request->id . '/final/' . $path_parts['filename'] . '.eml';
                        //shell_exec('cd /srv/candocs/'.$request->id.'/final/');
                        shell_exec($cmd1);
                        shell_exec('export DISPLAY=:0');
                        shell_exec($cmd2);
                    } elseif ($filexts == 'doc' || $filexts == 'docx') {
                        $cmd = 'cd /srv/candocs/' . $request->id . '/final && export HOME=/tmp && lowriter --headless --convert-to pdf ' . $file;
                        shell_exec($cmd);
                    }
                }
            }
            $listPdf = '';
            foreach ($files as $fk => $fv) {
                $listPdf .= $fv . " ";
            }
            $listPdf = substr($listPdf, 0, -1);
            shell_exec('cd /srv/candocs/' . $request->id . '/final && pdftk ' . $listPdf . ' cat output /srv/candocs/' . $request->id . '/' . $request->id . '_final.pdf');
            closedir($handle);
        }
        return $cmd1;
    }
    public function getFilename(Request $request){
        $id=$request->get('id');
        if($handle = @opendir('/srv/candocs/'.$id.'/final')){
            $files=[];
            while(false != ($file=readdir($handle))){
                if( $file!='.' && $file!='..' ){
                    $newfile = preg_replace("/[^a-zA-Z0-9.]/", "", $file);
                    @chmod('/srv/candocs/'.$id.'/final/'.$file, '0777');
                    $newfile = pathinfo($newfile, PATHINFO_FILENAME) . '.' . strtolower(pathinfo($newfile, PATHINFO_EXTENSION));
                    @rename('/srv/candocs/'.$id.'/final/'.$file, '/srv/candocs/'.$id.'/final/'.$newfile);
                    array_push($files,$file);
                }
            }
            closedir($handle);
            return response()->json($files);
        }
        else return response("Failed to open directory",401);
    }

    function downloadPackage($filename){
        $storage_path = storage_path('app/compress');
        return response()->download($storage_path.'/'.$filename,$filename);

    }
    public function compress(Request $request){
        if($request->hasFile('file')){
            $file = $request->file('file');
            $quality = $request->get('quality');
            $name = $file->getClientOriginalName();
            $mini_name = pathinfo($name,PATHINFO_FILENAME).'-mini.'.$file->getClientOriginalExtension();
            $storage_path = storage_path('app/compress');

            //$rawcmd = 'cd ' . $storage_path . '&& gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/ebook -dNOPAUSE -dBATCH  -dQUIET -sOutputFile=';
            //$rawcmd ='cd ' . $storage_path . ' && ./pdf-compress.sh ';
            if($quality =='normal')
                $rawcmd = 'cd '.$storage_path.'&& gs -sDEVICE=pdfwrite -r600 -dDownScaleFactor=3 -dCompatibilityLevel=1.3 -dPDFSETTINGS=/ebook -dNOPAUSE -dBATCH  -dQUIET -sOutputFile=';
            elseif ($quality=='black')
                $rawcmd = 'cd '.$storage_path.'&& gs -q -dNOPAUSE -dBATCH -dSAFER -sDEVICE=pdfwrite -sColorConversionStrategy=Gray -dCompatibilityLevel=1.4 -dPDFSETTINGS=/ebook -dEmbedAllFonts=true -dSubsetFonts=true -dColorImageDownsampleType=/Bicubic -dColorImageResolution=144 -dGrayImageDownsampleType=/Bicubic -dGrayImageResolution=144 -dMonoImageDownsampleType=/Bicubic -dMonoImageResolution=144 -sOutputFile=';
            $path = $file->storeAs('compress',$name);
            $size = Storage::size($path);
            $cmd = $rawcmd.escapeshellarg($mini_name).' '.escapeshellarg($name);
            shell_exec($cmd);
            $mini_size = Storage::size('/compress/'.$mini_name);
            //Storage::delete('/compress/'.$name);
            if($mini_size<$size) {
                $shrink = round((($size-$mini_size)/ $size) * 100, 2) . '%';
                $request->session()->flash('result',['status'=>'success','shrink'=>$shrink,'size'=>round($mini_size/1000000,1).' M','url'=>url('downloadPackage').'/'.$mini_name]);
                return redirect('largeFile');
                //return response()->download($storage_path . '/' . $mini_name);
                //return response()->download($storage_path . '/' . $mini_name)->deleteFileAfterSend(true);
            }
            else{
                return "Compress failure please contact IT for help";
            }
            // dd($name,$path,$size);
        }
        else return "Error: No file submit or file size larger than 30M";
    }

}
