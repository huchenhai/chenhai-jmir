<!doctype html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Chenhai Hu</title>
        <!-- Bootstrap Core CSS -->
        <link href="{{ URL::asset('css/bootstrap.min.css') }}" rel="stylesheet">
        <script src="{{ URL::asset('js/jquery-3.2.1.min.js') }}"></script>
        <script src="{{ URL::asset('js/bootstrap.min.js') }}"></script>
        <script src="{{ URL::asset('js/dropzone.js') }}"></script>
        <!-- Fonts -->


        <!-- Styles -->
    </head>
    <body>
    <div class="vid-container" style="
            background-image:url({{URL::asset('img/back.jpg')}});
            background-position: center;
            background-size: 100% 100%;
            background-repeat: no-repeat;
            ">
        {{--<video class="bgvid" autoplay="autoplay" muted="muted" preload="auto" loop>--}}
        {{--<source src="http://static-p.iuqo.com/media/home/home/video/cloud2.mp4" type="video/webm">--}}
        {{--</video>--}}
        <div class="box">
            <div class="container zone" style="margin-top:10%;">

                <h1><i class="fa fa-file-pdf-o"> Compress PDF</i></h1>
                <form id="file_form" action="{{url('/compressFile')}}" method="POST" enctype="multipart/form-data">

                    <select name="quality">
                        <option value="normal">Normal</option>
                        <option value="black">Black/White</option>
                    </select>
                    <input type="file" id="file" name="file">

                </form>

                <button id="open_btn">Start</button>
                @if (session('result')['status']=='success')
                    <ul><li>Reduce Size: {{session('result')['shrink']}}</li>
                        <li>Current Size: {{session('result')['size']}}</li>
                        <li>If it still larger than 10M, please cut a ticket</li>
                    </ul>
                    <a href="{{session('result')['url']}}"  class="a-download" id="download"><i class="fa fa-cloud-download" aria-hidden="true"> Download</i></a>
                @endif
            </div>
        </div>


    </div>
    @push("scripts")
    <!-- Custom CSS -->
    <link href="{{ URL::asset('css/landing.css') }}" rel="stylesheet">
    <script>
        $('#loader').hide();
        function hasExtension(inputID, exts) {
            var fileName = document.getElementById(inputID).value;
            return (new RegExp('(' + exts.join('|').replace(/\./g, '\\.') + ')$')).test(fileName);
        }
        $('#open_btn').on('click',function () {
            if(!hasExtension('file', ['.pdf']))
                alert('File type not allowed, pdf only');
            else {
                $(this).text('').attr('style',"background-image:url({{URL::asset('img/142.gif')}}); background-position:center; background-repeat: no-repeat;");
                $('#file_form').submit();
            }
        });
    </script>
    @endpush
    </body>
</html>
