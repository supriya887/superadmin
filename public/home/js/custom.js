// fix header
$(window).scroll(function () {
  if ($(this).scrollTop() > 0) {
    $(".g_header").addClass("g_shrink");
  } else {
    $(".g_header").removeClass("g_shrink");
  }
});
// fix header
// megha dropdown-menu
$(document).ready(function () {
  $(".navbar .navbar-nav .dropdown").hover(
    function () {
      $(this).find(".dropdown-menu").first().stop(true, true).fadeIn(800);
    },
    function () {
      $(this).find(".dropdown-menu").first().stop(true, true).fadeOut(500);
    }
  );
  // breakpoint and up
  $(window).resize(function () {
    if ($(window).width() >= 980) {
      // when you hover a toggle show its dropdown menu
      $(".navbar .navbar-nav .dropdown-toggle").hover(function () {
        $(this).parent().toggleClass("show");
        $(this).parent().find(".dropdown-menu").toggleClass("show");
      });
      // hide the menu when the mouse leaves the dropdown
      $(".navbar .navbar-nav .dropdown-menu").mouseleave(function () {
        $(this).removeClass("show");
      });
    }
  });
});
// megha dropdown-menu
//product-slider
var swiper = new Swiper(".banner-slider", {
  spaceBetween: 0,
  slidesPerView: 1,
  cssMode: true,
  autoplay: true,
  loop: false,
  autoplay: {
    delay: 4000,
    disableOnInteraction: false,
  },
  pagination: {
    el: ".swiper-pagination",
    type: "progressbar",
  },
  navigation: {
    nextEl: ".swiper-button-next",
    prevEl: ".swiper-button-prev",
  },
});
//product-slider
//product-slider
var swiper = new Swiper(".testimonial_slider", {
  spaceBetween: 30,
  slidesPerView: 2.4,
  cssMode: true,
  autoplay: true,
  loop: true,
  autoplay: {
    delay: 3000,
    disableOnInteraction: true,
    reverseDirection: true,
  },
  pagination: {
    el: ".swiper-pagination-testimonial",
    type: "progressbar",
  },
  navigation: {
    nextEl: ".swiper-button-next",
    prevEl: ".swiper-button-prev",
  },
  breakpoints: {
    480: {
      slidesPerView: 1.1,
      spaceBetween: 20,
    },
    767: {
      slidesPerView: 1.4,
      spaceBetween: 20,
    },
    991: {
      slidesPerView: 1.4,
      spaceBetween: 30,
    },
    1024: {
      slidesPerView: 1.4,
      spaceBetween: 30,
    },
    1600: {
      slidesPerView: 2.4,
      spaceBetween: 30,
    },
  },
});
//product-slider

//AOS
AOS.init({
  once: "",
  offset: 30,
  startEvent: "load",
  // easing: 'ease-in-out-sine',
  disable: "",
});
//AOS

// ===== Scroll to Top ====
$(window).scroll(function () {
  if ($(this).scrollTop() >= 50) {
    // If page is scrolled more than 50px
    $("#return-to-top").fadeIn(200); // Fade in the arrow
  } else {
    $("#return-to-top").fadeOut(200); // Else fade out the arrow
  }
});
$("#return-to-top").click(function () {
  // When arrow is clicked
  $("body,html").animate(
    {
      scrollTop: 0, // Scroll to top of body
    },
    200
  );
});
// ===== Scroll to Top ====

// accordion behavior in mobile 
document.addEventListener("DOMContentLoaded", () => {
  if (window.innerWidth > 1024) return;
  const tabs = document.querySelectorAll(".nav-tab-section .nav-link");
  const closeAll = () => {
    document.querySelectorAll(".tab-pane").forEach(p => {
      p.style.height = 0;
      p.classList.remove("active", "show");
    });
  };
  const openPane = pane => {
    pane.classList.add("active", "show");
    pane.style.height = "auto";
    pane.style.height = pane.scrollHeight + "px";
    pane.querySelectorAll("img").forEach(img => {
      if (!img.complete) img.onload = () =>
        pane.style.height = pane.scrollHeight + "px";
    });
  };
  tabs.forEach((tab, i) => {
    const pane = document.querySelector(tab.dataset.bsTarget);
    tab.closest(".nav-item").appendChild(pane);
    tab.addEventListener("shown.bs.tab", () => {
      closeAll();
      openPane(pane);
    });
    if (i === 0) requestAnimationFrame(() => openPane(pane));
  });
});
// accordion behavior in mobile 
// production-ready Quantity Input with Plus & Minus buttons
document.querySelectorAll('.qty-wrapper').forEach(wrapper => {
  const input = wrapper.querySelector('.qty-input');
  const plus = wrapper.querySelector('.plus');
  const minus = wrapper.querySelector('.minus');

  plus.addEventListener('click', () => {
    input.value = parseInt(input.value || 0) + 1;
  });

  minus.addEventListener('click', () => {
    if (parseInt(input.value) > 1) {
      input.value = parseInt(input.value) - 1;
    }
  });
});
// production-ready Quantity Input with Plus & Minus buttons

// more-details-box
document.addEventListener("DOMContentLoaded", function () {
  const MAX_VISIBLE = 6;

  document.querySelectorAll(".more-details-box").forEach(box => {
    const listItems = box.querySelectorAll(".plans-details-list li");
    const toggleBtn = box.querySelector(".more-details");

    // Hide items after first max visible count
    if (listItems.length > MAX_VISIBLE) {
      listItems.forEach((item, index) => {
        if (index >= MAX_VISIBLE) {
          item.classList.add("hidden");
        }
      });
    } else {
      // If less items, hide button
      if (toggleBtn) {
        toggleBtn.style.display = "none";
      }
      return;
    }

    let expanded = false;

    if (toggleBtn) {
      toggleBtn.addEventListener("click", function () {
        expanded = !expanded;

        listItems.forEach((item, index) => {
          if (index >= MAX_VISIBLE) {
            item.classList.toggle("hidden", !expanded);
          }
        });

        toggleBtn.textContent = expanded ? "View Less" : "See All Features";
      });
    }
  });
});

// more-details-box
// progress-loader
const loader = document.getElementById("loader");
const bar = document.getElementById("progress");

const images = document.images;
const total = images.length;
let loaded = 0;

if (total === 0) finishLoading();

for (let img of images) {
  if (img.complete) {
    increment();
  } else {
    img.addEventListener("load", increment);
    img.addEventListener("error", increment);
  }
}

function increment() {
  loaded++;
  const percent = Math.floor((loaded / total) * 100);
  bar.style.width = percent + "%";

  if (loaded >= total) {
    finishLoading();
  }
}

function finishLoading() {
  bar.style.width = "100%";
    setTimeout(() => {
      loader.classList.add("fade-out");
    }, 500);
  }

// progress-loader