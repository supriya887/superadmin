
lucide.createIcons({
   attrs: {'stroke-width': 1.2},
   nameAttr: 'data-lucide',
});

var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
  return new bootstrap.Tooltip(tooltipTriggerEl)
})

var swiper = new Swiper(".supportSwiper", {
  slidesPerView: 1.1,spaceBetween: 15,
  scrollbar: {el: ".swiper-scrollbar",hide: false,draggable: true,dragSize:80,snapOnRelease: true},
  mousewheel: {forceToAxis: false,releaseOnEdges: true,sensitivity:.5},
  breakpoints: {
    576: {slidesPerView: 1.4,spaceBetween: 20},
    768: {slidesPerView: 1.8,spaceBetween: 20},
    992: {slidesPerView: 2.1,spaceBetween: 20},
    1200: {slidesPerView: 2.4,spaceBetween: 20},
    1400: {slidesPerView: 2.8,spaceBetween: 20},
  },
});

 /*  Counters  */
 $(document).ready(function(){
var $animation_elements=$('.animateThis');
 $window=$(window);
 function check_if_in_view(){
  var window_height=$window.height();
  var window_top_position=$window.scrollTop();
  var window_bottom_position=(window_top_position+window_height);
  $.each($animation_elements,function(){
    var $element=$(this);
    var element_height=$element.outerHeight();
    var element_top_position=$element.offset().top+50;
    var element_bottom_position=(element_top_position+element_height);
    if((element_top_position<=window_bottom_position)){$element.addClass('in-view')}
    else{$element.removeClass('in-view')}})
}
$window.on('scroll resize',check_if_in_view);
$window.trigger('scroll');

 var a = 0;
 //$(window).scroll(function() {
   $('.count').each(function() {
     oTop  = $('.count').offset().top - window.innerHeight;
     if (a == 0 && $(window).scrollTop() > oTop) {
       var $this = $(this),
           countTo = $this.attr('data-count');
       $({countNum: $this.text()}).animate({countNum: countTo},
         {duration:2000,easing:'swing',
           step: function() {$this.text(Math.floor(this.countNum));},
           complete: function() {$this.text(this.countNum);}
       });
     }
   });
 //});

});