
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
</head>

<body>
    <div id="parent1">
      <div class="margin" style="position: relative; float:left; margin-left: 10px; margin-top: 50px; border: 3px solid black;">
        <video id="vidDisplay" style="height: 500px; width: 800px; display: inline-block; vertical-align: baseline;" onloadedmetadata="onPlay(this)" autoplay="true"></video>
        <canvas id="overlay" style="position: absolute; top: 0; left: 0;" width = "800" height = "500"></canvas>
      
      </div>
     
      <div id="parent2" style="float:left;">
       <br><br><br>
        <img id = "prof_img" style="margin-left: 150px; height:200px; width: 200px; border: 3px solid black; border-radius: 10px;" ></img><br><br>
        
        <div id="reg_disp" style="display: none;">
          <input id = "fname" style="margin-left:50px; width:400px; height: 30px; border-radius: 5px; border:1px solid black;" type="text" placeholder="First Name : "></input><br></br>
          <!-- <input id = "lname" style="margin-left:50px; width:500px; height: 30px; border-radius: 5px; border:1px solid black;" type="text" placeholder="Last Name : "></input><br></br><br> -->
          
          
          <input id = "lname" style="margin-left:50px; width:400px; height: 30px; border-radius: 5px; border:1px solid black;" type="text" placeholder="Last Name : "></input><br></br><br>
          
          <button id="capture" class="button button1" style="margin-left: 160px;height:80px;"> Capture Image</button><br><br><br><br>
          <div id="tries" style="margin-left: 80px; font-size: 23px; font-family:Lucida Console,Monaco,monospace; font-weight: bold;">Trials Left : </div>
        </div>

        <div id="log_disp">
            <br></br>
            <div id="logname" style="font-size: 35px; font-weight: bold; margin-left: 40px; width: 570px; white-space: pre-wrap; text-align: center;"></div><br>
            <div style="margin-left: 40px; width: 570px; border: 3px solid black;"></div><br>
        </div>
      </div>
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
          const options = getFaceDetectorOptions()
          const result = await faceapi.detectSingleFace(videoEl, options)
          if (result) {
            const dims = faceapi.matchDimensions(canvas, videoEl, true)
            dims.height = 500
            dims.width = 800
            canvas.height = 500
            canvas.width = 800
            const resizedResult = faceapi.resizeResults(result, dims)
            faceapi.draw.drawDetections(canvas, resizedResult)  
          }     
          else{
            $("#overlay").hide()
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
        
        url: "http://localhost:8000/fetch.php",
        data: ""
    }).done(async function(data) {
        if(data.length > 2){
          var json_str = "{\"parent\":" + data  + "}"
          content = JSON.parse(json_str)
          //console.log(content)
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
  $(document).ready(async function(){

    var counter = 5;
    const descriptions = [];
    // -------------Initialize---------------


        $("#reg_disp").show();
        $("#log_disp").hide();

        $(this).css('background-color','yellow')
        $(this).addClass('active')
        $("#reg_disp").show()
        $("#log_disp").hide()
        $("#prof_img").removeAttr('src')
        $("#fname").val('')
        $("#lname").val('')
        $("#logname").html('')
        $("#fname").prop("readonly", false)
        $("#lname").prop("readonly", false)      
        counter = 5
        description = []                
        $("#tries").html("Trials left : " + counter)
   

    $("#tries").html("Trials left : " + counter)

    $("#capture").click(async function(){
      var data = $("#lname").val() + "," + $("#fname").val();
      const label = data;

      if($("#fname").hasClass('active') && $("#lname").hasClass('active') && $("#fname").val() && $("#lname").val()){
        $("#fname").prop("readonly", true)
        $("#lname").prop("readonly", true)
        if(counter <= 5 && counter >= 0 ){
          var canvas = document.createElement('canvas');
          var context = canvas.getContext('2d');
          var video = document.getElementById('vidDisplay');
          context.drawImage(video, 0, 0, 600, 350);
          var capURL = canvas.toDataURL('image/png');
          var canvas2 = document.createElement('canvas');
          canvas2.width = 1200;
          canvas2.height = 800;
          var ctx = canvas2.getContext('2d');
          ctx.drawImage(video, 0, 0, 1200, 800);
          var new_image_url = canvas2.toDataURL();
          var img = document.createElement('img');
          img.src = new_image_url;
          document.getElementById("prof_img").src = img.src;

          const detections = await faceapi.detectSingleFace(img).withFaceLandmarks().withFaceDescriptor();
          if( detections != null){
            descriptions.push(detections.descriptor);
            var descrip = descriptions;
            counter--;
            $("#tries").html("Trials left : " + counter)
            if(counter == 0){
              waitingDialog.show('Processing data.............', {dialogSize: 'sm', progressType: 'success'})
              var postData = new faceapi.LabeledFaceDescriptors(label, descrip);
              $.ajax({
                  
                  type: "POST",
                  url: "http://localhost:8000/json.php",
                  
                  data: { myData: JSON.stringify(postData) },
                  
              })
              .done(async function (data) {
                  load_neural()
                  alert("Done!")
                  console.log("Success!")
                  waitingDialog.hide()
                  counter = 5
                  $("#tries").html("Trials left : " + counter)
                  $("#fname").val('')
                  $("#lname").val('')
                  $("#prof_img").removeAttr('src')                  
                  $("#fname").prop("readonly", false)
                  $("#lname").prop("readonly", false)
              })
              .fail(function (jqXHR, textStatus, errorThrown) { 
                console.log(jqXHR);
                console.log(textStatus);
                console.log(errorThrown);
                  alert("Error due to internet connection! Please try again!");
              });
              const descriptions = [];
            }          
          }
          else{
            alert("No FACE detected!");
          }
        }
        else{
          alert("Done Learning!");
          counter = 5;
          const descriptions = [];
        }
    
    }
    else{
      if(!$("#fname").val() || !$("#fname").hasClass('active')){
        $("#fname").css('border','1px solid red');
        $("#fname").removeClass('active')      
      }

      if(!$("#lname").val() || !$("#lname").hasClass('active')){
        $("#lname").css('border','1px solid red');
        $("#lname").removeClass('active')      
      }
    }
    });

    var format = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/;
    
    $("#fname").keyup(function(){
      var str = $(this).val().toUpperCase();
      $(this).val(str);
      if(format.test(str) && str == ""){
        $(this).css('border','1px solid red');
        $(this).removeClass('active')
      }
      else{
        $(this).css('border','3px solid black');
        $(this).addClass('active')
      }
    });

    $("#lname").keyup(function(){
      var str = $(this).val().toUpperCase();
      $(this).val(str);
      if(format.test(str) || str == ""){
        $(this).css('border','1px solid red');
        $(this).removeClass('active')
      }
      else{
        $(this).css('border','3px solid black')
        $(this).addClass('active')   
      }
    });
});
</script>

</html>

