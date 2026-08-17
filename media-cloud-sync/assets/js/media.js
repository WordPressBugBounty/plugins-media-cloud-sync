jQuery(function ($) {
	const media = wp.media;
	const globalData = window.wpmcs_media_object;
	if (media) {
		const MediaView = media.view;
		const Button = MediaView.Button;
		const AttachmentDetailColumn = MediaView.Attachment.Details.TwoColumn;

		if (AttachmentDetailColumn) {
			MediaView.Attachment.Details.TwoColumn =
				AttachmentDetailColumn.extend({
					render: function () {
						this.fetchProviderDetails(this.model.get("id"));
					},

					fetchProviderDetails: function (id) {
						wp.ajax
							.send("wpmcs_get_attachment_details", {
								data: {
									_nonce: globalData.file_details_nonce,
									id: id,
								},
							})
							.done(_.bind(this.renderView, this));
					},

					renderView: function (response) {
						// Render parent media.view.Attachment.Details
						AttachmentDetailColumn.prototype.render.apply(this);

						this.renderServerDetails(response);
					},

					renderServerDetails: function (response) {
						if (response.status) {
							var $detailsHtml = this.$el.find(".details");
							var data = response.data;
							var append = [];
							if (data.provider) {
								var $providerDiv = $("<div>", {
									class: "wpmcs_provider",
								});
								$providerDiv.append(
									$("<strong>").text(
										window.wpmcs_media_object.strings
											.provider
									)
								);
								$providerDiv.append(
									document.createTextNode(data.provider)
								);
								append.push($providerDiv);
							}
							if (data.region) {
								var $regionDiv = $("<div>", {
									class: "wpmcs_region",
								});
								$regionDiv.append(
									$("<strong>").text(
										window.wpmcs_media_object.strings
											.region
									)
								);
								$regionDiv.append(
									document.createTextNode(data.region)
								);
								append.push($regionDiv);
							}

							var accessString = data.private
								? window.wpmcs_media_object.strings
										.access_private
								: window.wpmcs_media_object.strings
										.access_public;
							var $accessDiv = $("<div>", {
								class: "wpmcs_access",
							});
							$accessDiv.append(
								$("<strong>").text(
									window.wpmcs_media_object.strings.access
								)
							);
							$accessDiv.append(
								document.createTextNode(accessString)
							);
							append.push($accessDiv);

							if (append.length) {
								append.forEach((element, key) => {
									$detailsHtml.append(element);
								});
							}
						}
					},
				});
		}
	}
});
