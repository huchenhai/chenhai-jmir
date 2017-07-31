<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\ZipArchive;
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    public function file(Request $request){
        //phpinfo();
        $file = $request->file('file')->store('doc');
        $path = storage_path($file);
        dd(file_exists($path));
        $content = self::read_file_docx($file);
        dd($path);
        $images = self::readZippedImages($file);
        $data = self::readJmirContent($content);
        return response()->json([
            'content' => $data,
            'images' =>$images
        ]);
    }
    public function read_file_docx($filename){

        $striped_content = '';
        $content = '';

        if(!$filename || !file_exists($filename)) return false;

        $zip = zip_open($filename);
        if (!$zip || is_numeric($zip)) return false;

        while ($zip_entry = zip_read($zip)) {

            if (zip_entry_open($zip, $zip_entry) == FALSE) continue;

            if (zip_entry_name($zip_entry) != "word/document.xml") continue;

            $content .= zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));

            zip_entry_close($zip_entry);
        }// end while

        zip_close($zip);

        //echo $content;
        //echo "<hr>";
        //file_put_contents('1.xml', $content);
//        $dom = new \DOMDocument();
//        $dom->loadXML($content);
//        dd($dom);
        $content = str_replace('</w:r></w:p></w:tc><w:tc>', " ", $content);
        $content = str_replace('</w:r></w:p>', "\r\n", $content);
        $striped_content = strip_tags($content);

        return $striped_content;
    }
    public function parseWord($file) {
        $content = "";
        $zip = new ZipArchive ( );
        if ($zip->open ($file) === TRUE ) {
            for($i = 0; $i < $zip->numFiles; $i ++) {
                $entry = $zip->getNameIndex ( $i );
                if (pathinfo ($entry,PATHINFO_BASENAME) == "document.xml") {
                    $zip->extractTo (pathinfo ($file, PATHINFO_DIRNAME ) . "/" . pathinfo ($file, PATHINFO_FILENAME ), array (
                        $entry
                    ) );
                    $filepath = pathinfo ($file, PATHINFO_DIRNAME ) . "/" . pathinfo ( $file, PATHINFO_FILENAME ) . "/" . $entry;
                    break;
                }
            }
            $zip->close ();
            return $filepath;
        } else {
            echo false;
        }
    }
    function readZippedImages($filename) {
        $images = [];

        /*Create a new ZIP archive object*/
        $zip = new ZipArchive;

        /*Open the received archive file*/
        if (true === $zip->open($filename)) {
            for ($i=0; $i<$zip->numFiles;$i++) {


                /*Loop via all the files to check for image files*/
                $zip_element = $zip->statIndex($i);


                /*Check for images*/
                if(preg_match("([^\s]+(\.(?i)(jpg|jpeg|png|gif|bmp))$)",$zip_element['name'])) {

                    array_push($images,base64_encode($zip->getFromIndex($i)));
                    /*Display images if present by using display.php*/

                }
            }
            return $images;
        }
    }
    public function readJmirContent($content){
        $key = ['Corresponding authors','Background','Objective','Methods','Results','Conclusions','Keywords'];
        $content = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $content);
        $content_all = preg_split("/((\r?\n)|(\r\n?))/", $content);
        $array=['Title'=>$content_all[1],'Authors'=>$content_all[2]];
        foreach($content_all as $line){
            if($key ==[])
                break;
            $k = $this->ifMatch($key,$line);
            if($k)
                $array[$k]=substr(strstr($line," "), 1);
        }
        return $array;
    }
    function ifMatch(&$key,$line){
        foreach ($key as $v){
            if(strpos($line,$v.":") === 0) {
                array_diff($key,array($v));
                return $v;
            }
        }
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
