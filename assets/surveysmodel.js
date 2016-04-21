/**
 * @surveysmodel.js Javascript to add HTML in admin page for LimeSurvey plugin surveysModel
 * @author Denis Chenu
 * @copyright Denis Chenu <http://www.sondages.pro>
 * @license magnet:?xt=urn:btih:d3d9a9a6595521f9666a5e94cc830dab83b65699&dn=expat.txt Expat (MIT)
 */
$(function() {
  // If copysurveylist : create a new and use it from json data
  if($('#copysurveylist').length)
  {
    var haveData=false;
    $.ajax({
      type: "POST",
      url: surveysModel.jsonUrl,
      dataType: "json",
      success: function(data) {
        if(data){
          $("<optgroup class='mastersurveyselect' label='Master'></optgroup>").insertAfter("#copysurveylist option:first");
          $.each(data, function(i, item) {
            $("#copysurveylist .mastersurveyselect").append("<option value='"+ item.value +"'>"+ item.label +"</option>");
          })
        }
      }
    });
  }
});

