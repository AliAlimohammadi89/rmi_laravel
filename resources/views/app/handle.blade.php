@extends('layout')

@section('content')


@if($process_status == 2)

     <script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">


    <script>
        $(function () {

            var interval;

            interval = setInterval(getProgress, 1000);
    //        interval_result = setInterval(getProgressResult, 1000);

            //Start receiving progress
            function getProgress(){
                $.ajax({
                    url: '{{ url("progress/?id=$process_id") }}',
                    success: function(data) {

                        $("progress").attr("value", data);

                        $p=(data *100)/"{{ $total_processes }}";
                        $("#progress").html(data);
                        $p1="width:"+$p+"%";

                      $("#progress2").attr("style",($p1));



                        if(data == "{{ $total_processes }}" || data == "-1"){
                            //getProgress();
                            clearInterval(interval);
                            $('.stopwatch').trigger( "pause" );
                            console.log('pause');
                            $('#error').removeClass('d-none').css('color', 'darkred').html('process has been completed');
                        }
                    }
                });
            }

            // Cache very important elements, especially the ones used always
            var element = $(this);
            var running = element.data('autostart');
            var hoursElement = element.find('.hours');
            var minutesElement = element.find('.minutes');
            var secondsElement = element.find('.seconds');
            var millisecondsElement = element.find('.milliseconds');
            var toggleElement = element.find('.toggle');
            var resetElement = element.find('.reset');
            var pauseText = toggleElement.data('pausetext');
            var resumeText = toggleElement.data('resumetext');
            var startText = toggleElement.text();

            // And it's better to keep the state of time in variables
            // than parsing them from the html.
            var hours, minutes, seconds, milliseconds, timer;


            // Never assume one widget is just used once in the page. You might
            // think of adding a second one. So, we adjust accordingly.
            $('.stopwatch').each(function () {


                $( this).on( "pause", function() {
                    console.log('pause');
                    pause();
                });

                function prependZero(time, length) {
                    // Quick way to turn number to string is to prepend it with a string
                    // Also, a quick way to turn floats to integers is to complement with 0
                    time = '' + (time | 0);
                    // And strings have length too. Prepend 0 until right.
                    while (time.length < length) time = '0' + time;
                    return time;
                }

                function setStopwatch(hours, minutes, seconds, milliseconds) {
                    // Using text(). html() will construct HTML when it finds one, overhead.
                    hoursElement.text(prependZero(hours, 2));
                    minutesElement.text(prependZero(minutes, 2));
                    secondsElement.text(prependZero(seconds, 2));
                    millisecondsElement.text(prependZero(milliseconds, 3));
                }

                // Update time in stopwatch periodically - every 25ms
                function runTimer() {
                    // Using ES5 Date.now() to get current timestamp
                    var startTime = Date.now();
                    var prevHours = hours;
                    var prevMinutes = minutes;
                    var prevSeconds = seconds;
                    var prevMilliseconds = milliseconds;

                    timer = setInterval(function () {
                        var timeElapsed = Date.now() - startTime;

                        hours = (timeElapsed / 3600000) + prevHours;
                        minutes = ((timeElapsed / 60000) + prevMinutes) % 60;
                        seconds = ((timeElapsed / 1000) + prevSeconds) % 60;
                        milliseconds = (timeElapsed + prevMilliseconds) % 1000;

                        setStopwatch(hours, minutes, seconds, milliseconds);
                    }, 25);
                }

                // Split out timer functions into functions.
                // Easier to read and write down responsibilities
                function run() {
                    running = true;
                    runTimer();
                    toggleElement.text(pauseText);
                }

                function pause() {
                    running = false;
                    clearTimeout(timer);
                    toggleElement.text(resumeText);
                }

                function reset() {
                    running = false;
                    pause();
                    hours = minutes = seconds = milliseconds = 0;
                    setStopwatch(hours, minutes, seconds, milliseconds);
                    toggleElement.text(startText);
                }

                // And button handlers merely call out the responsibilities
                toggleElement.on('click', function () {
                    (running) ? pause() : run();
                });

                resetElement.on('click', function () {
                    reset();
                });

                // Another advantageous thing about factoring out functions is that
                // They are reusable, callable elsewhere.
                reset();
                if(running) run();

                run();
            });
        });
    </script>


    <div>
         <progress class="progress-bar progress-bar-striped  " value="1" max="{{ $total_processes }}"></progress>
     </div>

    <div class="btn btn-info " style="margin: 1%">progress:</div>
    <div class="progress">
        <div class="progress-bar progress-bar-striped active" id="progress2"  role="progressbar"
             aria-valuenow="{{ 100 }}" aria-valuemin="0" aria-valuemax="{{ 100 }}" style="width:{{ 50 }}%">
            <p>imported products: <span id="progress">{{ $total_processes }}</span> of <span id="progress_all">{{ $total_processes }}</span></p>

        </div>
    </div>



        <div class="stopwatch btn btn-outline-success" data-autostart="false">
            <div class="time">
                <span class="hours"></span> :
                <span class="minutes"></span> :
                <span class="seconds"></span>
            </div>
        </div>

        <div id="error" class="alert alert-danger d-none"></div>
    @else

        {{--<div>--}}
            {{--progress:--}}
            {{--<progress value="{{ $total_processes }}" max="{{ $total_processes }}"></progress>--}}
            {{--<p>imported products: <span id="progress">{{ $total_processes }}</span> of <span id="progress_all">{{ $total_processes }}</span></p>--}}
        {{--</div>--}}

        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">

        <div class="btn badge-success " style="margin: 1%">progress:</div>
        <div class="progress">
            <div class="progress-bar progress-bar-striped " role="progressbar"
                 aria-valuenow="{{ 100 }}" aria-valuemin="0" aria-valuemax="{{ 100 }}" style="width:{{ 100 }}%">
                <p>imported products: <span id="progress">{{ $total_processes }}</span> of <span id="progress_all">{{ $total_processes }}</span></p>

            </div>
        </div>
        <span  class="btn btn-info"  >process has been completed</span>
    @endif


@endsection

