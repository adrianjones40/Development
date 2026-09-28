/*
 * Client-side validation and AJAX submission for #contact-form.
 * Server-side validation happens again in php/contact.php - never trust the client alone.
 */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    var form = document.getElementById("contact-form");
    if (!form) return;

    var messages = form.querySelector(".messages");
    var submitBtn = form.querySelector('button[type="submit"]');

    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    var PHONE_RE = /^[0-9+\-\s()]{7,20}$/;

    function fieldGroup(field) {
      return field.closest(".form-group");
    }

    function showError(field, message) {
      var group = fieldGroup(field);
      if (!group) return;
      field.classList.add("is-invalid");
      var help = group.querySelector(".help-block");
      if (help) help.textContent = message;
    }

    function clearError(field) {
      var group = fieldGroup(field);
      if (!group) return;
      field.classList.remove("is-invalid");
      var help = group.querySelector(".help-block");
      if (help) help.textContent = "";
    }

    function validators() {
      return {
        name: function (v) {
          if (!v.trim()) return "Name is required.";
          if (v.trim().length < 2) return "Name must be at least 2 characters.";
          return null;
        },
        email: function (v) {
          if (!v.trim()) return "Email is required.";
          if (!EMAIL_RE.test(v.trim())) return "Please enter a valid email address.";
          return null;
        },
        phone: function (v) {
          if (!v.trim()) return "Phone number is required.";
          if (!PHONE_RE.test(v.trim())) return "Please enter a valid phone number.";
          return null;
        },
        service: function (v) {
          if (!v.trim()) return "Please tell us which service you need.";
          if (v.trim().length < 2) return "Service must be at least 2 characters.";
          return null;
        },
        message: function (v) {
          if (!v.trim()) return "Please leave us a message.";
          if (v.trim().length < 10) return "Message must be at least 10 characters.";
          return null;
        }
      };
    }

    function validateField(field) {
      var rules = validators();
      var rule = rules[field.name];
      if (!rule) return true;
      var error = rule(field.value || "");
      if (error) {
        showError(field, error);
        return false;
      }
      clearError(field);
      return true;
    }

    var fields = form.querySelectorAll("input[name], textarea[name]");
    fields.forEach(function (field) {
      field.addEventListener("blur", function () {
        validateField(field);
      });
      field.addEventListener("input", function () {
        if (field.classList.contains("is-invalid")) validateField(field);
      });
    });

    function setMessage(type, text) {
      if (!messages) return;
      messages.textContent = text;
      messages.className = "messages alert alert-" + (type === "error" ? "danger" : "success");
      messages.style.display = "block";
    }

    form.addEventListener("submit", function (e) {
      e.preventDefault();

      var valid = true;
      fields.forEach(function (field) {
        if (!validateField(field)) valid = false;
      });

      if (!valid) {
        setMessage("error", "Please correct the highlighted fields and try again.");
        return;
      }

      if (submitBtn) submitBtn.disabled = true;
      setMessage("success", "Sending your message...");

      var formData = new FormData(form);

      fetch(form.getAttribute("action"), {
        method: "POST",
        body: formData,
        headers: { "X-Requested-With": "XMLHttpRequest" }
      })
        .then(function (response) {
          return response.json().catch(function () {
            throw new Error("Unexpected server response.");
          });
        })
        .then(function (data) {
          if (data && data.success) {
            setMessage("success", data.message || "Thank you! Your message has been sent.");
            form.reset();
          } else {
            var errText = (data && data.message) || "Something went wrong. Please try again later.";
            if (data && data.errors) {
              Object.keys(data.errors).forEach(function (name) {
                var field = form.querySelector('[name="' + name + '"]');
                if (field) showError(field, data.errors[name]);
              });
            }
            setMessage("error", errText);
          }
        })
        .catch(function () {
          setMessage("error", "Unable to send your message right now. Please try again later.");
        })
        .finally(function () {
          if (submitBtn) submitBtn.disabled = false;
        });
    });
  });
})();
