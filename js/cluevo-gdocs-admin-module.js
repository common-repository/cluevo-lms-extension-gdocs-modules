jQuery("document").ready(function(e) {
  var field = jQuery(
    '<label class="doc-name">' +
    cluevoGDocsStrings.titleLabel +
    '<input type="text" name="cluevo-gdocs-module-name" id="cluevo-gdocs-module-name" required/></label>'
  );
  jQuery(field).prependTo(
    jQuery('.cluevo-module-form[data-type="Google Documents"]')
  );
  jQuery(field).on("change", cluevoGdocsModuleHandleInput);

  jQuery(
    '.cluevo-add-module-overlay .module-description-container form.cluevo-ext-gdocs textarea[name="module-dl-url"]'
  ).off();

  jQuery(
    '.cluevo-add-module-overlay .module-description-container form.cluevo-ext-gdocs input[name="module-dl-url"], .cluevo-add-module-overlay .module-description-container form.cluevo-ext-gdocs textarea[name="module-dl-url"], #cluevo-gdocs-module-name'
  ).on("input", cluevoGdocsModuleHandleInput);
});

function cluevoGdocsModuleHandleInput(e) {
  var fileField = jQuery(this)
    .parents("form:first")
    .find('input[name="module-file"]');
  var urlField = jQuery(this)
    .parents("form:first")
    .find('[name="module-dl-url"]');
  var submitButton = jQuery(this)
    .parents("form:first")
    .find('input[type="submit"]');
  if (fileField.length > 0 && fileField.val() != "") {
    jQuery(fileField).val("");
    fileField.val("");
  }
  if (
    jQuery("#cluevo-gdocs-module-name").val() != "" &&
    urlField.val() != "" &&
    (urlField.val().startsWith('<iframe src="https://docs.google.com/') ||
      urlField.val().startsWith("https://docs.google.com/"))
  ) {
    submitButton.removeClass("disabled");
    submitButton.attr("disabled", false);
  } else {
    submitButton.addClass("disabled");
    submitButton.attr("disabled", "disabled");
  }
}
