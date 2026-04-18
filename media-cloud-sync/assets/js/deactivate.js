// Deactivation Form
jQuery(document).ready(function () {
	jQuery(document).on("click", function (e) {
		var popup = document.getElementById("dw-wpmcs-survey-form");
		var overlay = document.getElementById("dw-wpmcs-survey-form-wrap");
		var openButton = document.getElementById("deactivate-media-cloud-sync");

		if (e.target.id == "dw-wpmcs-survey-form-wrap") {
			dwWpmcsClosePopup();
		}
		if (e.target === openButton) {
			e.preventDefault();
			popup.style.display = "block";
			overlay.style.display = "block";
		}
		if (e.target.id == "dw-wpmcs-skip") {
			e.preventDefault();
			var urlRedirect = document
				.querySelector("a#deactivate-media-cloud-sync")
				.getAttribute("href");
			window.location = urlRedirect;
		}
		if (e.target.id == "dw-wpmcs-cancel") {
			e.preventDefault();
			dwWpmcsClosePopup();
		}
	});

	function dwWpmcsClosePopup() {
		var popup = document.getElementById("dw-wpmcs-survey-form");
		var overlay = document.getElementById("dw-wpmcs-survey-form-wrap");
		popup.style.display = "none";
		overlay.style.display = "none";
		jQuery("#dw-wpmcs-survey-form form")[0].reset();
		jQuery("#dw-wpmcs-survey-form form .dw-wpmcs-comments").hide();
		jQuery("#dw-wpmcs-error").html("");
	}

	jQuery("#dw-wpmcs-survey-form form").on("submit", function (e) {
		e.preventDefault();
		var valid = dwWpmcsValidate();
		if (valid) {
			var urlRedirect = document
				.querySelector("a#deactivate-media-cloud-sync")
				.getAttribute("href");
			var form = jQuery(this);
			var formArray = form.serializeArray();
			var jsonData = {};
			formArray.forEach(function (item) {
				jsonData[item.name] = item.value;
			});
			var actionUrl =
				"https://feedbacks.dudlewebs.com/api/submit-feedback.php";
			jQuery.ajax({
				type: "post",
				url: actionUrl,
				data: jsonData,
				dataType: "json",
				beforeSend: function () {
					jQuery("#dw-wpmcs-deactivate").prop("disabled", "disabled");
				},
				success: function (data) {
					window.location = urlRedirect;
				},
				error: function (jqXHR, textStatus, errorThrown) {
					window.location = urlRedirect;
				},
			});
		}
	});

	jQuery("#dw-wpmcs-survey-form .dw-wpmcs-comments textarea").on(
		"keyup",
		function () {
			dwWpmcsValidate();
		}
	);

	jQuery("#dw-wpmcs-survey-form form input[type='radio']").on(
		"change",
		function () {
			dwWpmcsValidate();
			let val = jQuery(this).val();
			if (
				val == "I found a bug" ||
				val == "Plugin suddenly stopped working" ||
				val == "Plugin broke my site" ||
				val == "Other" ||
				val == "Plugin doesn't meet my requirement"
			) {
				jQuery("#dw-wpmcs-survey-form form .dw-wpmcs-comments").show();
			} else {
				jQuery("#dw-wpmcs-survey-form form .dw-wpmcs-comments").hide();
			}
		}
	);

	function dwWpmcsValidate() {
		var error = "";
		var reason = jQuery(
			"#dw-wpmcs-survey-form form input[name='reason']:checked"
		).val();
		if (!reason) {
			error += "Please select your reason for deactivation";
		}
		if (
			error === "" &&
			(reason == "I found a bug" ||
				reason == "Plugin suddenly stopped working" ||
				reason == "Plugin broke my site" ||
				reason == "Other" ||
				reason == "Plugin doesn't meet my requirement")
		) {
			var comments = jQuery(
				"#dw-wpmcs-survey-form .dw-wpmcs-comments textarea"
			).val();
			if (comments.length <= 0) {
				error += "Please specify";
			}
		}
		if (error !== "") {
			jQuery("#dw-wpmcs-error").html(error);
			return false;
		}
		jQuery("#dw-wpmcs-error").html("");
		return true;
	}
});
