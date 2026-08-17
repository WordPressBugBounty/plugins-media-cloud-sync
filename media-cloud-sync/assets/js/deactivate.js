// Deactivation Form
jQuery(document).ready(function () {
	// NOTE: the "requirements" value below intentionally matches the literal
	// value="Plugin doesn\'t meet my requirements" attribute currently rendered
	// in includes/admin.php (the backslash is not a PHP-string escape there —
	// that markup is raw HTML, so it prints as a literal backslash character).
	var COMMENT_REQUIRED_REASONS = [
		"I found a bug",
		"Plugin suddenly stopped working",
		"Plugin broke my site",
		"Other",
		"Plugin doesn\\'t meet my requirements",
	];

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
		jQuery("#dw-wpmcs-survey-form .dw-wpmcs-comments textarea").removeClass(
			"has-error"
		);
		dwWpmcsSetError("");
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
		"input",
		function () {
			dwWpmcsValidate();
		}
	);

	jQuery("#dw-wpmcs-survey-form form input[type='radio']").on(
		"change",
		function () {
			var val = jQuery(this).val();
			if (COMMENT_REQUIRED_REASONS.indexOf(val) !== -1) {
				jQuery("#dw-wpmcs-survey-form form .dw-wpmcs-comments").show();
			} else {
				jQuery("#dw-wpmcs-survey-form form .dw-wpmcs-comments").hide();
			}
			dwWpmcsValidate();
		}
	);

	function dwWpmcsSetError(message) {
		jQuery("#dw-wpmcs-error-text").text(message);
		jQuery("#dw-wpmcs-error").toggleClass("is-visible", !!message);
	}

	function dwWpmcsValidate() {
		var reason = jQuery(
			"#dw-wpmcs-survey-form form input[name='reason']:checked"
		).val();
		var $textarea = jQuery("#dw-wpmcs-survey-form .dw-wpmcs-comments textarea");
		$textarea.removeClass("has-error");

		if (!reason) {
			dwWpmcsSetError("Please select a reason so we know how to improve");
			return false;
		}

		if (
			COMMENT_REQUIRED_REASONS.indexOf(reason) !== -1 &&
			$textarea.val().trim().length <= 0
		) {
			dwWpmcsSetError("Please add a few details so we can look into it");
			$textarea.addClass("has-error");
			return false;
		}

		dwWpmcsSetError("");
		return true;
	}
});
