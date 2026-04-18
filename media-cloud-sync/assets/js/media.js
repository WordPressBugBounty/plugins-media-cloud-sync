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
								var providerHtml =
									"<div class='wpmcs_provider'><strong>" +
									window.wpmcs_media_object.strings.provider +
									"</strong>" +
									data.provider +
									"</div>";
								append.push(providerHtml);
							}
							if (data.region) {
								var regionHtml =
									"<div class='wpmcs_region'><strong>" +
									window.wpmcs_media_object.strings.region +
									"</strong>" +
									data.region +
									"</div>";
								append.push(regionHtml);
							}

							var accessString = data.private
								? window.wpmcs_media_object.strings
										.access_private
								: window.wpmcs_media_object.strings
										.access_public;
							var accessHtml =
								"<div class='wpmcs_access'><strong>" +
								window.wpmcs_media_object.strings.access +
								"</strong>" +
								accessString +
								"</div>";

							append.push(accessHtml);

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
