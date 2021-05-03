<!DOCTYPE html>
<html>
<head>
  <title> Face Recognition </title>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.0/css/bootstrap.min.css">
  <link rel="stylesheet" type="text/css" href="style.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.0/js/bootstrap.min.js"></script>-
  <script src="face-api.min.js"></script>
  <script src="https://download.affectiva.com/js/3.2.1/affdex.js"></script>
  <script type="text/javascript" src="https://cdn.bootcss.com/echarts/4.1.0.rc2/echarts.min.js"></script>
</head>

<body>
    <div id="parent1">
      <div class="margin" style="position: relative; float:left; margin-left: 10px; margin-top: 0px; border: 3px solid black;">
        <video id="vidDisplay" style="height: 500px; width: 640px; display: inline-block; vertical-align: baseline;" onloadedmetadata="onPlay(this)" autoplay="true"></video>
        <canvas id="overlay" style="position: absolute; top: 0; left: 0;" width = "640" height = "500"></canvas>
  
      </div>

      <div style="float:left;">
        <div class="col-md-8 left">
          <div id="camera"></div>
        </div>

        <div id="game">
          <div id="target">?</div>
          <div id="score">?</div>
        </div>

        <div id="results-container">
          <div id="results"></div>
        </div>
      </div>

      <!-- <div id="parent2" style="float:left;">
        <div id="reg_disp" style="display: none;">
         
        </div>
      </div> -->
    </div>
</body>



<script>
  var waitingDialog = waitingDialog || (function ($) {
    var $dialog = $(
      '<div class="modal fade" data-backdrop="static" data-keyboard="false" tabindex="-1" role="dialog" aria-hidden="true" style="padding-top:15%; overflow-y:visible;">' +
        '<div class="modal-dialog modal-m">' +
          '<div class="modal-content">' +
          '<div class="modal-header"><h3 style="margin:0;"></h3></div>' +
          '<div class="modal-body">' +
            '<div class="progress progress-striped active" style="margin-bottom:0;"><div class="progress-bar" style="width: 100%"></div></div>' +
          '</div>' +
      '</div></div></div>');

  return {
    show: function (message, options) {
      if (typeof options === 'undefined') {
        options = {};
      }
      if (typeof message === 'undefined') {
        message = 'Loading';
      }
      var settings = $.extend({
        dialogSize: 'm',
        progressType: '',
        onHide: null 
      }, options);
      $dialog.find('.modal-dialog').attr('class', 'modal-dialog').addClass('modal-' + settings.dialogSize);
      $dialog.find('.progress-bar').attr('class', 'progress-bar');
      if (settings.progressType) {
        $dialog.find('.progress-bar').addClass('progress-bar-' + settings.progressType);
      }
      $dialog.find('h3').text(message);
      if (typeof settings.onHide === 'function') {
        $dialog.off('hidden.bs.modal').on('hidden.bs.modal', function (e) {
          settings.onHide.call($dialog);
        });
      }
      $dialog.modal();
    },
    hide: function () {
      $dialog.modal('hide');
    }
  };

})(jQuery);
</script>


<script>

  //----------------------------GLOBAL VARIABLE FOR FACE MATCHER------------------------------------
  var faceMatcher = undefined
  //----------------------------------------------------------------------------------------------

  waitingDialog.show('Initializing data....', {dialogSize: 'sm', progressType: 'success'})
  $("#parent1").hide();
  $("#parent2").hide();
  Promise.all([
    faceapi.nets.faceRecognitionNet.loadFromUri('./models'),
    faceapi.nets.faceLandmark68Net.loadFromUri('./models'),
    faceapi.nets.ssdMobilenetv1.loadFromUri('./models'),
    faceapi.nets.tinyFaceDetector.loadFromUri('./models')
  ]).then(start)

  async function start() {
    
    $.ajax({
        
        dataType:"json",
        url: "http://localhost:8000/fetch.php",
        data:""
        
    }).done(async function(data) {
      console.log(data.length)
        if(data.length >= 1){
          var json_str = {parent: data}
          
          content = JSON.parse(JSON.stringify(json_str))
         // console.log(typeof(content))

          for (var x = 0; x < Object.keys(content.parent).length; x++) {
            for (var y = 0; y < Object.keys(content.parent[x]._descriptors).length; y++) {
              var results = Object.values(content.parent[x]._descriptors[y])
              content.parent[x]._descriptors[y] = new Float32Array(results)
            }
          }

          faceMatcher = await createFaceMatcher(content);
        }
        waitingDialog.hide()
        $('#parent1').show()
        $('#parent2').show()        
        run();
    }).fail(function()  {
    alert("Sorry. Server unavailable. ");
}); 
  }

  // Create Face Matcher
  async function createFaceMatcher(data) {
    const labeledFaceDescriptors = await Promise.all(data.parent.map(className => {
      const descriptors = [];
      for (var i = 0; i < className._descriptors.length; i++) {
        descriptors.push(className._descriptors[i]);
      }
      return new faceapi.LabeledFaceDescriptors(className._label, descriptors);
    }))
    return new faceapi.FaceMatcher(labeledFaceDescriptors,0.7);
  }


  async function onPlay() {
      const videoEl = $('#vidDisplay').get(0)
      if(videoEl.paused || videoEl.ended )
        return setTimeout(() => onPlay())

        $("#overlay").show()
        const canvas = $('#overlay').get(0)

          if(faceMatcher != undefined && cnt_blink != 0){
            //--------------------------FACE RECOGNIZE------------------
            const input = document.getElementById('vidDisplay')
            const displaySize = { width: 640, height: 500 }
            faceapi.matchDimensions(canvas, displaySize)
            const detections = await faceapi.detectAllFaces(input).withFaceLandmarks().withFaceDescriptors()
            const resizedDetections = faceapi.resizeResults(detections, displaySize)
            const results = resizedDetections.map(d => faceMatcher.findBestMatch(d.descriptor))
            results.forEach((result, i) => {
                const box = resizedDetections[i].detection.box

              //naam karan------------------

                const drawBox = new faceapi.draw.DrawBox(box, { label: result.toString() })
                drawBox.draw(canvas)
                var str = result.toString()
                rating = parseFloat(str.substring(str.indexOf('(') + 1,str.indexOf(')')))
                str = str.substring(0, str.indexOf('('))
                str = str.substring(0, str.length - 1)
                console.log(str)
                if(str != "unknown"){
                  if(rating < 0.5){
                        if(str == $("#log_name").text()){
                            console.log("Match TRUE!")
                            match = true;
                           
                        }
                    }  
                }
            })
  
          }
      

      setTimeout(() => onPlay())
    }

  async function run() {
      const stream = await navigator.mediaDevices.getUserMedia({ video: {} })
      const videoEl = $('#vidDisplay').get(0)
      videoEl.srcObject = stream
  }
  
  // tiny_face_detector options
  let inputSize = 160
  let scoreThreshold = 0.4

  function getFaceDetectorOptions() {
    return  new faceapi.TinyFaceDetectorOptions({ inputSize, scoreThreshold });
  }

  async function load_neural(){
    waitingDialog.show('Initializing neural data....', {dialogSize: 'sm', progressType: 'success'})
    $.ajax({
        dataType: 'json',
        url: "http://localhost:8000/fetch.php",
        data: ""
    }).done(async function(data) {
        if(data.length > 2){
          var json_str = "{\"parent\":" + data  + "}"
          content = JSON.parse(json_str)
          console.log(content)
          for (var x = 0; x < Object.keys(content.parent).length; x++) {
            for (var y = 0; y < Object.keys(content.parent[x]._descriptors).length; y++) {
              var results = Object.values(content.parent[x]._descriptors[y]);
              content.parent[x]._descriptors[y] = new Float32Array(results);
            }
          }
          faceMatcher = await createFaceMatcher(content);
        }
        waitingDialog.hide()
    });
  }

</script>

<script>



  //EYEEEEEEEEEEEEEEEEEEEEE BLINKKKKKKKKKKKKKKKK


  // The affdex SDK Needs to create video and canvas elements in the DOM
var divRoot = $("#camera")[0];  // div node where we want to add these elements
var width = 640, height = 500;  // camera image size
var faceMode = affdex.FaceDetectorMode.LARGE_FACES;  // face mode parameter

// Initialize an Affectiva CameraDetector object
var detector = new affdex.CameraDetector(divRoot, width, height, faceMode);

// Enable detection of specific Expressions classifiers.
detector.detectExpressions.eyeClosure=true;

// Set process rate
detector.processFPS = 15

// --- History data and utillities ---
var data = {}  // history data
var cnt_result  // count results returned
var e_prev = null
var e_now  // capture eye status
var e_threshhold = 10  // eyeClosure threshhold
var cnt_blink  // count blinks
var t_prev, t_now // capture refresh interval

function initData() {
  data['attention'] = []
  data['eyeClosure'] = []
  data['timestamp'] = []
  data['interval'] = []
  cnt_result = 0
  cnt_blink = 0
  t_prev = null
}

// load data from LocalStorage
function loadData() {
  if (window.localStorage) {
    var localStorage =window.localStorage;
    for (k in data) {
      data[k] = localStorage.getItem(k);
    }
  }
}


// --- Echarts setup ---

// initialize echarts instance
var myChart = echarts.init($('#results')[0]);

// initialize options
function initChart() {
  myChart.setOption({
      grid: {
        left: 0,
        top: 10,
        right: 0,
        bottom: 5,
        containLabel: true
      },
      xAxis: {
        data: []
      },
      yAxis: {
        splitLine: {
          show: false
        }
      },
      visualMap: {
        show: false,
        pieces: [{
          gte: 0,
          lt: 10,
          color: '#50a3ba'
        }],
        outOfRange: {
          color: '#d94e5d'
        }
      },
      series: [{
        name: 'eyeClosure',
        type: 'line',        
        lineStyle: {
          width: 1
        },
        areaStyle: {
          opacity: 0.5
        },
        data: []
      }]
  });
}

// --- Utility functions ---

// Display log messages and tracking results
function log(node_name, msg) {
  $(node_name).append("<span>" + msg + "</span><br />")
}

// clear previous output
function clearOutput() {
  $("#logs").html("");  
  $('#appearance').html("");
  $('#target').html("?");
  $('#score').html("?");
  initChart();
}

// --- Callback functions ---

// Start button
function onStart() {
  if (detector && !detector.isRunning) {
    clearOutput();  // clear previous output
    initData();
    initChart();
    detector.start();  // start detector 
  }
  log('#logs', "Start button pressed");
}



// Add a callback to notify when camera access is allowed
detector.addEventListener("onWebcamConnectSuccess", function() {
  log('#logs', "Webcam access allowed");
});

// Add a callback to notify when camera access is denied
detector.addEventListener("onWebcamConnectFailure", function() {
  log('#logs', "webcam denied");
  console.log("Webcam access denied");
});

// Add a callback to notify when detector is stopped
detector.addEventListener("onStopSuccess", function() {
  log('#logs', "The detector reports stopped");
});

// Add a callback to notify when the detector is initialized and ready for running
detector.addEventListener("onInitializeSuccess", function() {
  log('#logs', "The detector reports initialized");
  //Display canvas instead of video feed because we want to draw the feature points on it
  $("#face_video_canvas").css("display", "block");
  $("#face_video").css("display", "none");

  // TODO(optional): Call a function to initialize the game, if needed
  // <your code here>
});

// Add a callback to receive the results from processing an image
// NOTE: The faces object contains a list of the faces detected in the image,
//   probabilities for different expressions, emotions and appearance metrics
detector.addEventListener("onImageResultsSuccess", function(faces, image, timestamp) {
  var canvas = $('#face_video_canvas')[0];
  if (!canvas)
    return;

  // Count results
  cnt_result += 1;

  // Time interval
  t_prev = t_now;
  t_now = timestamp;
  interval = t_prev == null ? 0 : t_now - t_prev;
  data['interval'].push(parseInt(interval*1000));

  // Report face metrics
  // $('#results').html("");
  $('#appearance').html("");
  timestamp = timestamp.toFixed(1);
  data['timestamp'].push(timestamp);
  log('#appearance', "Timestamp: " + timestamp);
  log('#appearance', "Total results: " + cnt_result);
  log('#appearance', "Results/sec: " + (cnt_result/timestamp).toFixed(1));
  log('#appearance', "Faces found: " + faces.length);
  if (faces.length > 0) {
    // Report desired metrics
    eyeClosure = parseInt(faces[0].expressions.eyeClosure);
    log('#appearance', "eyeClosure: " + eyeClosure);

    // Mark eye keypoint
    drawEye(canvas, image, faces[0]);

    // Count blinks
    e_prev = e_now;
    e_now = eyeClosure<e_threshhold ? 0 : 1;
    if (t_prev != null && e_prev==1 && e_now==0) {
      if(faces.length == 1){
        cnt_blink += 1;
      }
      
    }
    $('#target').html(cnt_blink);
    if (cnt_blink>0) {
      $('#score').html((timestamp/cnt_blink).toFixed(1) + ' sec/blink');
    }

    // Add data
    data['eyeClosure'].push(eyeClosure);   
  }
  else {
    // append nan
    data['eyeClosure'].push(NaN);
  }

  // update chart
  myChart.setOption({
    xAxis: {
      data: data.timestamp
    },      
    series: [{
      name: 'eyeClosure',
      data: data.eyeClosure
    }]
  });
});


// --- Custom functions ---

// Draw eye feature points
function drawEye(canvas, img, face) {
  // Obtain a 2D context object to draw on the canvas
  var ctx = canvas.getContext('2d');

  // TODO: Set the stroke and/or fill style you want for each feature point marker
  // See: https://developer.mozilla.org/en-US/docs/Web/API/CanvasRenderingContext2D#Fill_and_stroke_styles
  ctx.strokeStyle="#FFF";
  
  // Loop over each feature point in the face  
  // 16 Outer Right Eye
  // 17 Inner Right Eye
  // 18 Inner Left Eye
  // 19 Outer Left Eye
  // 30 Upper Corner Right Eye
  // 31 Lower Corner Right Eye
  // 32 Upper Corner Left Eye
  // 33 Lower Corner Left Eye
  eyepoints = [16, 17, 18, 19, 30, 31, 32, 33]
  for (var id in eyepoints) {
    var featurePoint = face.featurePoints[eyepoints[id]];

    // TODO: Draw feature point, e.g. as a circle using ctx.arc()
    // See: https://developer.mozilla.org/en-US/docs/Web/API/CanvasRenderingContext2D/arc
    ctx.beginPath();
    ctx.arc(featurePoint['x'],featurePoint['y'],2,0,2*Math.PI);
    ctx.stroke();
  }
}

  
  $(document).ready(async function(){

    var counter = 5;
    const descriptions = [];
    // -------------Initialize---------------



 //   $("#reg_disp").hide();

    onStart();

   
    // console.log("CLICKED!!!!!")
      $.ajax({
        dataType: "json",
        url: "http://localhost:8000/fetch.php",
        data: ""
      }).done(function(data) {
          labeled = data;  // CHANGES------------
      });
      $(this).css('background-color','yellow')
      $(this).addClass('active')
   //   $("#reg_disp").hide()
      $("#logname").html('')
      counter = 5
      description = []          
  
  
    var format = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/;
});
</script>

</html>

