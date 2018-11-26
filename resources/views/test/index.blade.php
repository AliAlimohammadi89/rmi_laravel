<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script type="text/javascript">
    //Start the long running process
    $.ajax({
        url: "{{ url('test/long_progress') }}",
        success: function(data) {
            $('#progress_all').html(data);
        }
    });
    //Start receiving progress
    function getProgress(){
        $.ajax({
            url: "{{ url('test/progress') }}",
            success: function(data) {
//                $("#progress").html(data);
                $("progress").attr("value", data);
                if(data == 100){
                    //getProgress();
                    clearInterval(interval);
                }
            }
        });
    }
    //getProgress();

    var interval = setInterval(getProgress, 1000);
</script>

progress:
<progress value="1" max="100"></progress>
<p>imported products: <div id="progress"></div> of <div id="progress_all"></div></p>
