document.addEventListener("DOMContentLoaded", function () {

    /* =========================================================
       LUCIDE ICONS
    ========================================================= */

    function refreshIcons() {

        if (typeof lucide !== "undefined") {
            lucide.createIcons();
        }

    }

    refreshIcons();



    /* =========================================================
       COMMON HELPERS
    ========================================================= */

    function showToast(title, message) {

        const toast = document.getElementById("leadToast");

        if (!toast) return;

        const toastTitle = document.getElementById("toastTitle");
        const toastMessage = document.getElementById("toastMessage");

        if (toastTitle) {
            toastTitle.textContent = title;
        }

        if (toastMessage) {
            toastMessage.textContent = message;
        }

        toast.classList.add("show");

        clearTimeout(window.toastTimer);

        window.toastTimer = setTimeout(function () {
            toast.classList.remove("show");
        }, 3500);

    }


    function closeToast() {

        const toast = document.getElementById("leadToast");

        if (toast) {
            toast.classList.remove("show");
        }

    }



    const closeToastBtn =
        document.getElementById("closeToast");

    if (closeToastBtn) {

        closeToastBtn.addEventListener(
            "click",
            closeToast
        );

    }



    /* =========================================================
       MODAL HELPERS
    ========================================================= */

    function openModal(modal) {

        if (!modal) return;

        modal.classList.add("show");
        modal.setAttribute("aria-hidden", "false");

        document.body.classList.add("modal-open");

        refreshIcons();

    }


    function closeModal(modal) {

        if (!modal) return;

        modal.classList.remove("show");
        modal.setAttribute("aria-hidden", "true");

        document.body.classList.remove("modal-open");

    }
        /* =========================================================
       LEAD EDIT
    ========================================================= */

    const editLeadBtn =
        document.getElementById("editLeadBtn");

    const leadViewMode =
        document.getElementById("leadViewMode");

    const leadEditForm =
        document.getElementById("leadEditForm");

    const cancelLeadEdit =
        document.getElementById("cancelLeadEdit");


    if (editLeadBtn) {

        editLeadBtn.addEventListener(
            "click",
            function () {

                if (!leadViewMode || !leadEditForm) {
                    return;
                }

                leadViewMode.style.display = "none";

                leadEditForm.style.display = "block";

                editLeadBtn.style.display = "none";


                const firstInput =
                    leadEditForm.querySelector(
                        "input, select, textarea"
                    );


                if (firstInput) {

                    setTimeout(function () {

                        firstInput.focus();

                    }, 100);

                }

            }
        );

    }


    if (cancelLeadEdit) {

        cancelLeadEdit.addEventListener(
            "click",
            function () {

                if (!leadViewMode || !leadEditForm) {
                    return;
                }

                leadEditForm.style.display = "none";

                leadViewMode.style.display = "grid";


                if (editLeadBtn) {

                    editLeadBtn.style.display =
                        "inline-flex";

                }

            }
        );

    }



    /* =========================================================
       SAVE LEAD
    ========================================================= */

    if (leadEditForm) {

        leadEditForm.addEventListener(
            "submit",
            function (e) {

                e.preventDefault();


                if (!leadEditForm.checkValidity()) {

                    leadEditForm.reportValidity();

                    return;

                }


                const formData =
                    new FormData(leadEditForm);


                const fullName =
                    formData.get("full_name");

                const designation =
                    formData.get("designation");

                const contactNo =
                    formData.get("contact_no");

                const email =
                    formData.get("email");

                const industry =
                    formData.get("industry");

                const employees =
                    formData.get("employees");

                const gstin =
                    formData.get("gstin");

                const address =
                    formData.get("address");


                const items =
                    leadViewMode.querySelectorAll(
                        ".info-item"
                    );


                if (items.length >= 8) {

                    items[0]
                        .querySelector("p")
                        .textContent = fullName;

                    items[1]
                        .querySelector("p")
                        .textContent = designation;

                    items[2]
                        .querySelector("p")
                        .textContent = contactNo;

                    items[3]
                        .querySelector("p")
                        .textContent = email;

                    items[4]
                        .querySelector("p")
                        .textContent = industry;

                    items[5]
                        .querySelector("p")
                        .textContent = employees;

                    items[6]
                        .querySelector("p")
                        .textContent =
                            gstin || "-";

                    items[7]
                        .querySelector("p")
                        .textContent = address;

                }


                leadEditForm.style.display = "none";

                leadViewMode.style.display = "grid";


                if (editLeadBtn) {

                    editLeadBtn.style.display =
                        "inline-flex";

                }


                addActivity(
                    "Lead information updated.",
                    "Today"
                );


                showToast(
                    "Lead Updated",
                    "Lead information saved successfully."
                );

            }
        );

    }    /* =========================================================
    CONTACT MANAGEMENT
 ========================================================= */

 const addContactBtn =
     document.getElementById("addContactBtn");

 const contactList =
     document.getElementById("contactList");

 const contactEmptyState =
     document.getElementById("contactEmptyState");


 let contactCounter = 2;


 function updateContactEmptyState() {

     if (!contactList || !contactEmptyState) {
         return;
     }

     const cards =
         contactList.querySelectorAll(
             ".lead-contact-card"
         );

     contactEmptyState.style.display =
         cards.length === 0
             ? "flex"
             : "none";

 }


 function createContactCard() {

     contactCounter++;

     const contactNumber =
         contactCounter;


     const card =
         document.createElement("div");

     card.className =
         "lead-contact-card";

     card.dataset.contactId =
         contactNumber;


     card.innerHTML = `

         <div class="contact-card-header">

             <h4>
                 Contact Person ${contactNumber}
             </h4>

             <div class="contact-actions">

                 <button
                     type="button"
                     class="contact-edit-btn"
                     title="Edit Contact"
                 >
                     <i data-lucide="pencil"></i>
                 </button>

                 <button
                     type="button"
                     class="contact-delete-btn"
                     title="Delete Contact"
                 >
                     <i data-lucide="trash-2"></i>
                 </button>

             </div>

         </div>


         <div class="contact-view">

             <div class="contact-info-grid">

                 <div>
                     <label>Full Name</label>
                     <p>-</p>
                 </div>

                 <div>
                     <label>Designation</label>
                     <p>-</p>
                 </div>

                 <div>
                     <label>Email</label>
                     <p>-</p>
                 </div>

                 <div>
                     <label>Contact No.</label>
                     <p>-</p>
                 </div>

             </div>

         </div>


         <form class="contact-edit-form">

             <div class="contact-info-grid">

                 <div>

                     <label>
                         Full Name
                         <span>*</span>
                     </label>

                     <input
                         type="text"
                         name="contact_name"
                         class="form-control"
                         required
                     >

                 </div>


                 <div>

                     <label>
                         Designation
                         <span>*</span>
                     </label>

                     <input
                         type="text"
                         name="contact_designation"
                         class="form-control"
                         required
                     >

                 </div>


                 <div>

                     <label>
                         Email
                         <span>*</span>
                     </label>

                     <input
                         type="email"
                         name="contact_email"
                         class="form-control"
                         required
                     >

                 </div>


                 <div>

                     <label>
                         Contact No.
                         <span>*</span>
                     </label>

                     <input
                         type="tel"
                         name="contact_phone"
                         class="form-control"
                         required
                     >

                 </div>

             </div>


             <div class="contact-edit-actions">

                 <button
                     type="button"
                     class="cancel-contact-btn"
                 >
                     Cancel
                 </button>

                 <button
                     type="submit"
                     class="save-contact-btn"
                 >
                     Save
                 </button>

             </div>

         </form>

     `;


     const form =
         card.querySelector(
             ".contact-edit-form"
         );

     form.style.display = "block";


     contactList.appendChild(card);

     refreshIcons();

     updateContactEmptyState();


     const firstInput =
         form.querySelector("input");


     if (firstInput) {
         firstInput.focus();
     }

 }


 if (addContactBtn) {

     addContactBtn.addEventListener(
         "click",
         createContactCard
     );

 }



 /* =========================================================
    CONTACT EVENTS
 ========================================================= */

 if (contactList) {

     contactList.addEventListener(
         "click",
         function (e) {

             const editBtn =
                 e.target.closest(
                     ".contact-edit-btn"
                 );

             const deleteBtn =
                 e.target.closest(
                     ".contact-delete-btn"
                 );

             const cancelBtn =
                 e.target.closest(
                     ".cancel-contact-btn"
                 );


             const card =
                 e.target.closest(
                     ".lead-contact-card"
                 );


             if (!card) return;



             /* =========================
                EDIT CONTACT
             ========================= */

             if (editBtn) {

                 const view =
                     card.querySelector(
                         ".contact-view"
                     );

                 const form =
                     card.querySelector(
                         ".contact-edit-form"
                     );


                 if (view) {
                     view.style.display = "none";
                 }

                 if (form) {
                     form.style.display = "block";
                 }


                 const values =
                     view.querySelectorAll("p");

                 const inputs =
                     form.querySelectorAll("input");


                 if (
                     values.length === 4 &&
                     inputs.length === 4
                 ) {

                     inputs[0].value =
                         values[0].textContent.trim() === "-"
                             ? ""
                             : values[0].textContent.trim();

                     inputs[1].value =
                         values[1].textContent.trim() === "-"
                             ? ""
                             : values[1].textContent.trim();

                     inputs[2].value =
                         values[2].textContent.trim() === "-"
                             ? ""
                             : values[2].textContent.trim();

                     inputs[3].value =
                         values[3].textContent.trim() === "-"
                             ? ""
                             : values[3].textContent.trim();

                 }


                 const firstInput =
                     form.querySelector("input");


                 if (firstInput) {
                     firstInput.focus();
                 }

             }



             /* =========================
                DELETE CONTACT
             ========================= */

             if (deleteBtn) {

                 const contactName =
                     card.querySelector(
                         ".contact-view p"
                     );


                 const name =
                     contactName
                         ? contactName.textContent.trim()
                         : "this contact";


                 const confirmed =
                     confirm(
                         "Delete " + name + "?"
                     );


                 if (!confirmed) {
                     return;
                 }


                 card.remove();

                 updateContactEmptyState();


                 addActivity(
                     "Contact removed from lead.",
                     "Today"
                 );


                 showToast(
                     "Contact Deleted",
                     "Contact removed successfully."
                 );

             }



             /* =========================
                CANCEL CONTACT EDIT
             ========================= */

             if (cancelBtn) {

                 const form =
                     card.querySelector(
                         ".contact-edit-form"
                     );

                 const view =
                     card.querySelector(
                         ".contact-view"
                     );


                 const isNewEmptyCard =
                     card.querySelector(
                         ".contact-view p"
                     )?.textContent.trim() === "-";


                 if (isNewEmptyCard) {

                     card.remove();

                     updateContactEmptyState();

                     return;

                 }


                 if (form) {
                     form.style.display = "none";
                 }

                 if (view) {
                     view.style.display = "block";
                 }

             }

         }
     );



     /* =====================================================
        SAVE CONTACT
     ===================================================== */

     contactList.addEventListener(
         "submit",
         function (e) {

             const form =
                 e.target.closest(
                     ".contact-edit-form"
                 );


             if (!form) return;


             e.preventDefault();


             if (!form.checkValidity()) {

                 form.reportValidity();

                 return;

             }


             const card =
                 form.closest(
                     ".lead-contact-card"
                 );

             const view =
                 card.querySelector(
                     ".contact-view"
                 );

             const inputs =
                 form.querySelectorAll("input");

             const values =
                 view.querySelectorAll("p");


             values[0].textContent =
                 inputs[0].value.trim();

             values[1].textContent =
                 inputs[1].value.trim();

             values[2].textContent =
                 inputs[2].value.trim();

             values[3].textContent =
                 inputs[3].value.trim();


             form.style.display =
                 "none";

             view.style.display =
                 "block";


             addActivity(
                 "Contact information updated.",
                 "Today"
             );


             showToast(
                 "Contact Saved",
                 "Contact information updated successfully."
             );

         }
     );

 }    /* =========================================================
 SERVICE MANAGEMENT
========================================================= */

const addServiceBtn =
  document.getElementById("addServiceBtn");

const serviceList =
  document.getElementById("serviceList");

const serviceEmptyState =
  document.getElementById("serviceEmptyState");

const serviceModal =
  document.getElementById("serviceModal");

const serviceForm =
  document.getElementById("serviceForm");

const closeServiceModal =
  document.getElementById("closeServiceModal");

const cancelServiceModal =
  document.getElementById("cancelServiceModal");

const serviceModalTitle =
  document.getElementById("serviceModalTitle");

const serviceId =
  document.getElementById("serviceId");

const serviceName =
  document.getElementById("serviceName");

const planName =
  document.getElementById("planName");

const quotationPeriod =
  document.getElementById("quotationPeriod");

const renewalDate =
  document.getElementById("renewalDate");

const serviceRate =
  document.getElementById("serviceRate");

const serviceUsers =
  document.getElementById("serviceUsers");

const serviceNote =
  document.getElementById("serviceNote");


let serviceCounter = 2;
let deleteTargetService = null;



/* =========================================================
 RESET SERVICE FORM
========================================================= */

function resetServiceForm() {

  if (!serviceForm) return;

  serviceForm.reset();

  if (serviceId) {
      serviceId.value = "";
  }

  if (serviceModalTitle) {
      serviceModalTitle.textContent =
          "Add Service";
  }


  const saveBtn =
      document.getElementById(
          "saveServiceBtn"
      );


  if (saveBtn) {

      saveBtn.innerHTML = `
          <i data-lucide="check"></i>
          Save Service
      `;

  }


  refreshIcons();

}



/* =========================================================
 OPEN ADD SERVICE MODAL
========================================================= */

function openAddServiceModal() {

  resetServiceForm();

  openModal(serviceModal);

}


if (addServiceBtn) {

  addServiceBtn.addEventListener(
      "click",
      openAddServiceModal
  );

}


if (closeServiceModal) {

  closeServiceModal.addEventListener(
      "click",
      function () {

          closeModal(serviceModal);

      }
  );

}


if (cancelServiceModal) {

  cancelServiceModal.addEventListener(
      "click",
      function () {

          closeModal(serviceModal);

      }
  );

}



/* =========================================================
 CREATE SERVICE CARD
========================================================= */

function createServiceCard(data) {

  if (!serviceList) return null;

  serviceCounter++;

  const id =
      data.id || serviceCounter;


  const rate =
      Number(data.rate || 0);


  const users =
      Number(data.users || 0);


  const total =
      rate * users;


  const card =
      document.createElement("div");


  card.className =
      "service-required-card";


  card.dataset.serviceId =
      id;

  card.dataset.rate =
      rate;

  card.dataset.users =
      users;

  card.dataset.note =
      data.note || "";


  card.innerHTML = `

      <div class="service-check">

          <input
              type="checkbox"
              class="service-checkbox"
              name="selected_services[]"
              value="${id}"
          >

      </div>


      <div class="service-item">

          <label>
              Service
          </label>

          <p>
              ${escapeHTML(data.service)}
          </p>

      </div>


      <div class="service-item">

          <label>
              Plan Name
          </label>

          <p>
              ${escapeHTML(data.planName)}
          </p>

      </div>


      <div class="service-item">

          <label>
              Quotation Period
          </label>

          <p>
              ${escapeHTML(data.period)}
          </p>

      </div>


      <div class="service-item">

          <label>
              Renewal Date
          </label>

          <p>
              ${formatDate(data.renewalDate)}
          </p>

      </div>


      <div class="service-item">

          <label>
              Rate
          </label>

          <p>
              ₹ ${formatNumber(rate)}
              per user/${getPeriodShortName(data.period)}
          </p>

      </div>


      <div class="service-item">

          <label>
              No. of Users
          </label>

          <p>
              ${users}
          </p>

      </div>


      <div class="service-actions">

          <button
              type="button"
              class="service-edit-btn"
              title="Edit Service"
          >
              <i data-lucide="pencil"></i>
          </button>


          <button
              type="button"
              class="service-delete-btn"
              title="Delete Service"
          >
              <i data-lucide="trash-2"></i>
          </button>

      </div>

  `;


  serviceList.appendChild(card);

  refreshIcons();

  updateServiceState();

  return card;

}



/* =========================================================
 ESCAPE HTML
========================================================= */

function escapeHTML(value) {

  if (
      value === null ||
      value === undefined
  ) {
      return "";
  }


  return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");

}



/* =========================================================
 NUMBER FORMAT
========================================================= */

function formatNumber(number) {

  return new Intl.NumberFormat(
      "en-IN",
      {
          maximumFractionDigits: 2
      }
  ).format(number);

}



/* =========================================================
 DATE FORMAT
========================================================= */

function formatDate(dateString) {

  if (!dateString) {
      return "-";
  }


  const date =
      new Date(dateString);


  if (isNaN(date.getTime())) {
      return dateString;
  }


  const day =
      String(
          date.getDate()
      ).padStart(2, "0");


  const month =
      String(
          date.getMonth() + 1
      ).padStart(2, "0");


  const year =
      date.getFullYear();


  return `${day}/${month}/${year}`;

}



/* =========================================================
 PERIOD SHORT NAME
========================================================= */

function getPeriodShortName(period) {

  switch (period) {

      case "Monthly":
          return "month";

      case "Quarterly":
          return "quarter";

      case "Half Yearly":
          return "6 months";

      case "Yearly":
          return "year";

      case "Biennial":
          return "2 years";

      default:
          return "year";

  }

}



/* =========================================================
 SERVICE STATE
========================================================= */

function updateServiceState() {

  if (!serviceList) return;


  const cards =
      serviceList.querySelectorAll(
          ".service-required-card"
      );


  if (serviceEmptyState) {

      serviceEmptyState.style.display =
          cards.length === 0
              ? "flex"
              : "none";

  }


  updateQuotationSummary();

}    /* =========================================================
SERVICE EDIT / DELETE
========================================================= */

if (serviceList) {

 serviceList.addEventListener(
     "click",
     function (e) {

         const editBtn =
             e.target.closest(
                 ".service-edit-btn"
             );

         const deleteBtn =
             e.target.closest(
                 ".service-delete-btn"
             );


         const card =
             e.target.closest(
                 ".service-required-card"
             );


         if (!card) return;



         /* =========================
            EDIT SERVICE
         ========================= */

         if (editBtn) {

             const items =
                 card.querySelectorAll(
                     ".service-item p"
                 );


             if (serviceId) {
                 serviceId.value =
                     card.dataset.serviceId;
             }


             if (serviceName) {
                 serviceName.value =
                     items[0]?.textContent.trim() || "";
             }


             if (planName) {
                 planName.value =
                     items[1]?.textContent.trim() || "";
             }


             if (quotationPeriod) {
                 quotationPeriod.value =
                     items[2]?.textContent.trim() || "";
             }


             const dateText =
                 items[3]?.textContent.trim() || "";


             if (
                 renewalDate &&
                 dateText
             ) {

                 const parts =
                     dateText.split("/");


                 if (parts.length === 3) {

                     renewalDate.value =
                         `${parts[2]}-${parts[1]}-${parts[0]}`;

                 }

             }


             const rateText =
                 items[4]?.textContent
                     .replace(/[^\d.]/g, "")
                     .trim();


             if (serviceRate) {
                 serviceRate.value =
                     rateText || "";
             }


             if (serviceUsers) {
                 serviceUsers.value =
                     items[5]?.textContent.trim() || "";
             }


             if (serviceNote) {
                 serviceNote.value =
                     card.dataset.note || "";
             }


             if (serviceModalTitle) {

                 serviceModalTitle.textContent =
                     "Edit Service";

             }


             const saveBtn =
                 document.getElementById(
                     "saveServiceBtn"
                 );


             if (saveBtn) {

                 saveBtn.innerHTML = `
                     <i data-lucide="check"></i>
                     Update Service
                 `;

             }


             openModal(serviceModal);

             refreshIcons();

         }



         /* =========================
            DELETE SERVICE
         ========================= */

         if (deleteBtn) {

             deleteTargetService =
                 card;


             const deleteModal =
                 document.getElementById(
                     "deleteServiceModal"
                 );


             openModal(deleteModal);

         }

     }
 );



 /* =====================================================
    SERVICE CHECKBOX
 ===================================================== */

 serviceList.addEventListener(
     "change",
     function (e) {

         if (
             e.target.classList.contains(
                 "service-checkbox"
             )
         ) {

             updateQuotationSummary();

         }

     }
 );

}



/* =========================================================
SAVE / UPDATE SERVICE
========================================================= */

if (serviceForm) {

 serviceForm.addEventListener(
     "submit",
     function (e) {

         e.preventDefault();


         if (!serviceForm.checkValidity()) {

             serviceForm.reportValidity();

             return;

         }


         const id =
             serviceId
                 ? serviceId.value
                 : "";


         const data = {

             service:
                 serviceName
                     ? serviceName.value.trim()
                     : "",

             planName:
                 planName
                     ? planName.value.trim()
                     : "",

             period:
                 quotationPeriod
                     ? quotationPeriod.value
                     : "",

             renewalDate:
                 renewalDate
                     ? renewalDate.value
                     : "",

             rate:
                 serviceRate
                     ? Number(serviceRate.value)
                     : 0,

             users:
                 serviceUsers
                     ? Number(serviceUsers.value)
                     : 0,

             note:
                 serviceNote
                     ? serviceNote.value.trim()
                     : ""

         };



         /* =========================
            EDIT EXISTING SERVICE
         ========================= */

         if (id) {

             const card =
                 serviceList.querySelector(
                     `[data-service-id="${id}"]`
                 );


             if (card) {

                 const items =
                     card.querySelectorAll(
                         ".service-item p"
                     );


                 items[0].textContent =
                     data.service;

                 items[1].textContent =
                     data.planName;

                 items[2].textContent =
                     data.period;

                 items[3].textContent =
                     formatDate(
                         data.renewalDate
                     );

                 items[4].textContent =
                     `₹ ${formatNumber(data.rate)} per user/${getPeriodShortName(data.period)}`;

                 items[5].textContent =
                     data.users;


                 card.dataset.rate =
                     data.rate;

                 card.dataset.users =
                     data.users;

                 card.dataset.note =
                     data.note;


                 showToast(
                     "Service Updated",
                     "Service details updated successfully."
                 );

             }

         }



         /* =========================
            ADD NEW SERVICE
         ========================= */

         else {

             createServiceCard(data);


             showToast(
                 "Service Added",
                 "New service added successfully."
             );

         }


         addActivity(
             id
                 ? "Service information updated."
                 : "New service added.",
             "Today"
         );


         closeModal(serviceModal);

         resetServiceForm();

         updateServiceState();

     }
 );

}    /* =========================================================
DELETE SERVICE MODAL
========================================================= */

const deleteServiceModal =
 document.getElementById(
     "deleteServiceModal"
 );

const cancelDeleteService =
 document.getElementById(
     "cancelDeleteService"
 );

const confirmDeleteService =
 document.getElementById(
     "confirmDeleteService"
 );


if (cancelDeleteService) {

 cancelDeleteService.addEventListener(
     "click",
     function () {

         deleteTargetService =
             null;

         closeModal(
             deleteServiceModal
         );

     }
 );

}


if (confirmDeleteService) {

 confirmDeleteService.addEventListener(
     "click",
     function () {

         if (deleteTargetService) {

             deleteTargetService.remove();

             updateServiceState();


             addActivity(
                 "Service removed from lead.",
                 "Today"
             );


             showToast(
                 "Service Deleted",
                 "Service removed successfully."
             );

         }


         deleteTargetService =
             null;


         closeModal(
             deleteServiceModal
         );

     }
 );

}



/* =========================================================
QUOTATION SUMMARY
========================================================= */

function updateQuotationSummary() {

 const checkboxes =
     document.querySelectorAll(
         ".service-checkbox:checked"
     );


 let total = 0;


 checkboxes.forEach(
     function (checkbox) {

         const card =
             checkbox.closest(
                 ".service-required-card"
             );


         if (!card) return;


         const rate =
             Number(
                 card.dataset.rate || 0
             );


         const users =
             Number(
                 card.dataset.users || 0
             );


         total +=
             rate * users;

     }
 );


 const count =
     document.getElementById(
         "selectedServiceCount"
     );


 const totalElement =
     document.getElementById(
         "estimatedTotal"
     );


 if (count) {

     count.textContent =
         checkboxes.length;

 }


 if (totalElement) {

     totalElement.textContent =
         `₹ ${formatNumber(total)}`;

 }

}    /* =========================================================
CREATE QUOTATION
========================================================= */

const createQuotationBtn =
 document.getElementById(
     "createQuotationBtn"
 );

const quotationModal =
 document.getElementById(
     "quotationModal"
 );

const quotationPreviewList =
 document.getElementById(
     "quotationPreviewList"
 );

const quotationModalTotal =
 document.getElementById(
     "quotationModalTotal"
 );


if (createQuotationBtn) {

 createQuotationBtn.addEventListener(
     "click",
     function () {

         const selected =
             document.querySelectorAll(
                 ".service-checkbox:checked"
             );


         if (selected.length === 0) {

             showToast(
                 "Select Service",
                 "Please select at least one service."
             );

             return;

         }


         if (!quotationPreviewList) {
             return;
         }


         quotationPreviewList.innerHTML =
             "";


         let total = 0;


         selected.forEach(
             function (checkbox) {

                 const card =
                     checkbox.closest(
                         ".service-required-card"
                     );


                 if (!card) return;


                 const items =
                     card.querySelectorAll(
                         ".service-item p"
                     );


                 const service =
                     items[0]?.textContent.trim()
                     || "-";


                 const plan =
                     items[1]?.textContent.trim()
                     || "-";


                 const period =
                     items[2]?.textContent.trim()
                     || "-";


                 const rate =
                     Number(
                         card.dataset.rate || 0
                     );


                 const users =
                     Number(
                         card.dataset.users || 0
                     );


                 const amount =
                     rate * users;


                 total += amount;


                 const row =
                     document.createElement(
                         "div"
                     );


                 row.className =
                     "quotation-preview-item";


                 row.innerHTML = `

                     <div>

                         <strong>
                             ${escapeHTML(service)}
                         </strong>

                         <span>
                             ${escapeHTML(plan)}
                         </span>

                         <small>
                             ${escapeHTML(period)}
                             · ${users} Users
                         </small>

                     </div>


                     <strong>
                         ₹ ${formatNumber(amount)}
                     </strong>

                 `;


                 quotationPreviewList.appendChild(
                     row
                 );

             }
         );


         if (quotationModalTotal) {

             quotationModalTotal.textContent =
                 `₹ ${formatNumber(total)}`;

         }


         openModal(
             quotationModal
         );

     }
 );

}



/* =========================================================
QUOTATION MODAL BUTTONS
========================================================= */

const closeQuotationModal =
 document.getElementById(
     "closeQuotationModal"
 );

const cancelQuotation =
 document.getElementById(
     "cancelQuotation"
 );

const confirmQuotation =
 document.getElementById(
     "confirmQuotation"
 );


if (closeQuotationModal) {

 closeQuotationModal.addEventListener(
     "click",
     function () {

         closeModal(
             quotationModal
         );

     }
 );

}


if (cancelQuotation) {

 cancelQuotation.addEventListener(
     "click",
     function () {

         closeModal(
             quotationModal
         );

     }
 );

}


if (confirmQuotation) {

 confirmQuotation.addEventListener(
     "click",
     function () {

         const selected =
             document.querySelectorAll(
                 ".service-checkbox:checked"
             );


         if (selected.length === 0) {

             showToast(
                 "No Service",
                 "Please select a service first."
             );

             return;

         }


         /*
          * Backend/API call yahan add kar sakte ho.
          *
          * Example:
          *
          * fetch("api/create-quotation.php", {
          *     method: "POST",
          *     body: formData
          * });
          */


         addActivity(
             "Quotation created from selected services.",
             "Today"
         ); 


         closeModal(
             quotationModal
         );


         showToast(
             "Quotation Created",
             "Quotation created successfully."
         );

     }
 );

}
    /* =========================================================
       ASSIGNED USER DROPDOWN
    ========================================================= */

    const assignedUser =
        document.getElementById(
            "assignedUser"
        );

    const assignedUserDropdown =
        document.getElementById(
            "assignedUserDropdown"
        );

    const assignedTo =
        document.getElementById(
            "assignedTo"
        );

    const assignedUserName =
        document.getElementById(
            "assignedUserName"
        );


    /*
     * IMPORTANT:
     * assignedUserDropdown exist karta hai tabhi
     * click event attach hoga.
     * Isse "Cannot read properties of null"
     * wala error nahi aayega.
     */

    if (
        assignedUser &&
        assignedUserDropdown
    ) {

        assignedUser.addEventListener(
            "click",
            function (e) {

                e.stopPropagation();


                assignedUserDropdown.classList.toggle(
                    "show"
                );


                const expanded =
                    assignedUserDropdown.classList.contains(
                        "show"
                    );


                assignedUser.setAttribute(
                    "aria-expanded",
                    expanded
                );

            }
        );

    }



    /* =========================================================
       ASSIGNED USER OPTIONS
    ========================================================= */

    if (assignedUserDropdown) {

        assignedUserDropdown.addEventListener(
            "click",
            function (e) {

                const option =
                    e.target.closest(
                        ".assigned-user-option"
                    );


                if (!option) {
                    return;
                }


                const userId =
                    option.dataset.userId;


                const userName =
                    option.dataset.userName;


                const initial =
                    option.dataset.userInitial;


                if (assignedTo) {

                    assignedTo.value =
                        userId || "";

                }


                if (assignedUserName) {

                    assignedUserName.textContent =
                        userName || "";

                }


                if (assignedUser) {

                    const avatar =
                        assignedUser.querySelector(
                            ".user-avatar"
                        );


                    if (avatar) {

                        avatar.textContent =
                            initial || "";

                    }

                }


                assignedUserDropdown
                    .querySelectorAll(
                        ".assigned-user-option"
                    )
                    .forEach(
                        function (item) {

                            item.classList.remove(
                                "active"
                            );

                        }
                    );


                option.classList.add(
                    "active"
                );


                assignedUserDropdown.classList.remove(
                    "show"
                );


                if (assignedUser) {

                    assignedUser.setAttribute(
                        "aria-expanded",
                        "false"
                    );

                }

            }
        );

    }



    /* =========================================================
       CLOSE ASSIGNED DROPDOWN OUTSIDE
    ========================================================= */

    document.addEventListener(
        "click",
        function (e) {

            if (
                assignedUser &&
                assignedUserDropdown &&
                !assignedUser.contains(e.target) &&
                !assignedUserDropdown.contains(e.target)
            ) {

                assignedUserDropdown.classList.remove(
                    "show"
                );


                assignedUser.setAttribute(
                    "aria-expanded",
                    "false"
                );

            }

        }
    );
        /* =========================================================
       FOLLOW-UP FORM
    ========================================================= */

    const followupForm =
        document.getElementById(
            "followupForm"
        );


    if (followupForm) {

        followupForm.addEventListener(
            "submit",
            function (e) {

                e.preventDefault();


                if (!followupForm.checkValidity()) {

                    followupForm.reportValidity();

                    return;

                }


                const category =
                    document.getElementById(
                        "leadCategory"
                    )?.value;


                const status =
                    document.getElementById(
                        "leadStatus"
                    )?.value;


                const datetime =
                    document.getElementById(
                        "followupDateTime"
                    )?.value;


                const note =
                    document.getElementById(
                        "followupNote"
                    )?.value.trim();


                /*
                 * Backend save yahan add kar sakte ho.
                 */


                addActivity(
                    `Follow-up scheduled. Status: ${status}.`,
                    formatActivityDate(datetime)
                );


                showToast(
                    "Follow-up Saved",
                    "Follow-up has been scheduled successfully."
                );


                /*
                 * Note:
                 * Form reset nahi kar rahe because
                 * selected values visible rehne chahiye.
                 */

            }
        );

    }



    /* =========================================================
       ACTIVITY
    ========================================================= */

    const activityList =
        document.getElementById(
            "activityList"
        );

    const activityEmpty =
        document.getElementById(
            "activityEmpty"
        );


    function addActivity(
        message,
        date
    ) {

        if (!activityList) return;


        const item =
            document.createElement(
                "div"
            );


        item.className =
            "activity-item";


        item.dataset.activityId =
            Date.now();


        item.innerHTML = `

            <span class="activity-dot"></span>

            <div class="activity-content">

                <strong>
                    ${escapeHTML(date)}
                </strong>

                <p>
                    ${escapeHTML(message)}
                </p>

            </div>

        `;


        activityList.insertBefore(
            item,
            activityList.firstChild
        );


        if (activityEmpty) {

            activityEmpty.style.display =
                "none";

        }

    }



    /* =========================================================
       ACTIVITY DATE
    ========================================================= */

    function formatActivityDate(value) {

        if (!value) {
            return "Today";
        }


        const date =
            new Date(value);


        if (isNaN(date.getTime())) {
            return "Today";
        }


        const months = [

            "Jan",
            "Feb",
            "Mar",
            "Apr",
            "May",
            "Jun",
            "Jul",
            "Aug",
            "Sep",
            "Oct",
            "Nov",
            "Dec"

        ];


        return `${String(
            date.getDate()
        ).padStart(2, "0")} ${
            months[date.getMonth()]
        } ${date.getFullYear()}`;

    }
        /* =========================================================
       VIEW ALL ACTIVITY
    ========================================================= */

    const viewAllActivityBtn =
        document.getElementById(
            "viewAllActivityBtn"
        );

    const activityModal =
        document.getElementById(
            "activityModal"
        );

    const closeActivityModal =
        document.getElementById(
            "closeActivityModal"
        );

    const fullActivityList =
        document.getElementById(
            "fullActivityList"
        );


    if (viewAllActivityBtn) {

        viewAllActivityBtn.addEventListener(
            "click",
            function () {

                if (
                    activityList &&
                    fullActivityList
                ) {

                    fullActivityList.innerHTML =
                        activityList.innerHTML;

                }


                openModal(
                    activityModal
                );

            }
        );

    }


    if (closeActivityModal) {

        closeActivityModal.addEventListener(
            "click",
            function () {

                closeModal(
                    activityModal
                );

            }
        );

    }



    /* =========================================================
       MODAL OVERLAY CLOSE
    ========================================================= */

    document
        .querySelectorAll(
            ".modal-overlay"
        )
        .forEach(
            function (overlay) {

                overlay.addEventListener(
                    "click",
                    function () {

                        const modal =
                            overlay.closest(
                                ".custom-modal"
                            );


                        if (modal) {

                            closeModal(
                                modal
                            );

                        }

                    }
                );

            }
        );



    /* =========================================================
       ESC KEY CLOSE MODAL
    ========================================================= */

    document.addEventListener(
        "keydown",
        function (e) {

            if (e.key !== "Escape") {
                return;
            }


            document
                .querySelectorAll(
                    ".custom-modal.show"
                )
                .forEach(
                    function (modal) {

                        closeModal(
                            modal
                        );

                    }
                );

        }
    );



    /* =========================================================
       INITIAL STATE
    ========================================================= */

    updateContactEmptyState();

    updateServiceState();

    updateQuotationSummary();

    refreshIcons();

});


document.addEventListener("DOMContentLoaded", function () {

    const quotations = document.querySelectorAll(".quotation-preview");

    quotations.forEach(function (quotation) {

        const header = quotation.querySelector(".quotation-header");

        if (!header) return;

        /*
         * Create quotation-body automatically
         */
        let body = quotation.querySelector(".quotation-body");

        if (!body) {

            body = document.createElement("div");
            body.className = "quotation-body";

            /*
             * Header ke baad ke saare elements
             * body ke andar move karo
             */
            while (header.nextElementSibling) {
                body.appendChild(header.nextElementSibling);
            }

            quotation.appendChild(body);
        }


        /*
         * Header click
         */
        header.addEventListener("click", function () {

            quotation.classList.toggle("closed");

        });

    });

});

function closeQuotationPopup() {
    const popup = document.querySelector(".quotation-popup");

    if (!popup) return;

    popup.classList.remove("show");
    popup._anchor = null;
}

function positionQuotationPopup(popup, button) {
    const rect = button.getBoundingClientRect();
    const gap = 8;
    const width = popup.offsetWidth || 230;
    const height = popup.offsetHeight || 220;

    let left = rect.right - width;
    if (left < gap) left = gap;
    if (left + width > window.innerWidth - gap) {
        left = window.innerWidth - width - gap;
    }

    let top = rect.bottom + gap;
    if (top + height > window.innerHeight - gap) {
        top = rect.top - height - gap;
    }
    if (top < gap) top = gap;

    popup.style.top = Math.round(top) + "px";
    popup.style.left = Math.round(left) + "px";
    popup.style.right = "auto";
}

window.toggleQuotationPopup = function (button, event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const popup = document.querySelector(".quotation-popup");

    if (!popup || !button) {
        console.error("Quotation popup not found.");
        return;
    }

    if (popup.classList.contains("show") && popup._anchor === button) {
        closeQuotationPopup();
        return;
    }

    popup.classList.add("show");
    popup._anchor = button;
    positionQuotationPopup(popup, button);

    if (window.lucide) {
        lucide.createIcons();
    }
};

document.addEventListener("DOMContentLoaded", function () {

    /* =========================================================
       CLICK OUTSIDE POPUP
    ========================================================= */

    document.addEventListener("click", function (event) {

        if (
            event.target.closest(".more-btn") ||
            event.target.closest(".quotation-popup")
        ) {
            return;
        }

        closeQuotationPopup();

    });

    window.addEventListener("scroll", closeQuotationPopup, true);
    window.addEventListener("resize", closeQuotationPopup);


    /* =========================================================
       ESC KEY
    ========================================================= */

    document.addEventListener("keydown", function (event) {

        if (event.key === "Escape") {
            closeQuotationPopup();
        }

    });


    /* =========================================================
       QUOTATION ACTIONS
    ========================================================= */

    window.viewQuotation = function () {

        console.log("View Quotation");

        // Example:
        // window.location.href = "view-quotation.php";

    };


    window.editQuotation = function () {

        console.log("Edit Quotation");

        // Example:
        // window.location.href = "edit-quotation.php";

    };


    window.downloadQuotation = function () {

        console.log("Download Quotation");

        // Example:
        // window.open("quotation.pdf", "_blank");

    };


    window.sendQuotation = function () {

        console.log("Send Quotation");

    };


    /* =========================================================
       DISCOUNT
    ========================================================= */

    window.sendDiscountRequest = function () {

        console.log("Send for more discount");

    };


    /* =========================================================
       INVOICE
    ========================================================= */

    window.downloadInvoice = function () {

        console.log("Download Invoice");

    };


    window.sendInvoice = function () {

        console.log("Send Invoice");

    };

});