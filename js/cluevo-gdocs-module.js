jQuery(document).ready(function () {
  jQuery(".cluevo-module-link.cluevo-module-mode-lightbox").click(
    async function (e) {
      e.preventDefault();
      const data = jQuery(this).data();
      const type = data.moduleType;
      const itemId = data.itemId;
      const moduleId = data.moduleId;
      if (type === "google docs") {
        cluevoOpenLightbox(data, "gdocs");
        cluevoShowLightbox();
        const response = await cluevo_ext_gdocs_get_module(moduleId);
        if (response) {
          cluevoChangeLightboxContent(response.html);
        }
      }
    }
  );
  jQuery(".cluevo-module-link.cluevo-module-mode-popup").click(async function (
    e
  ) {
    e.preventDefault();
    const data = jQuery(this).data();
    const type = data.moduleType;
    const itemId = data.itemId;
    const moduleId = data.moduleId;
    if (type === "google docs") {
      const response = await cluevo_ext_gdocs_get_module(moduleId);
      if (response) {
        const gdocsWindow = window.open(response.href);
        gdocsWindow.onbeforeunload = function (e) {
          cluevo_ext_gdocs_save_progress({
            moduleId,
            itemId,
            max: 100,
            score: 100,
          });
        };
      } else {
        console.error("error");
      }
    }
  });

  jQuery(document).on(
    "click",
    "#cluevo-module-lightbox-overlay.gdocs div.cluevo-close-button",
    function () {
      const moduleId = document.querySelector("#cluevo-module-lightbox-overlay")
        .dataset.moduleId;
      const itemId =
        document.querySelector("#cluevo-module-lightbox-overlay").dataset
          .itemId ?? null;
      cluevo_ext_gdocs_save_progress({
        moduleId,
        itemId,
        max: 100,
        score: 100,
      });
      closeLightbox();
    }
  );
});

function cluevo_ext_gdocs_save_progress({ moduleId, itemId, max, score }) {
  const data = {
    id: moduleId ?? null,
    itemId: itemId ?? null,
    max: max,
    score: score,
  };

  let url = cluevoWpApiSettings.root;
  if (itemId) {
    url += "cluevo/v1/items/" + itemId + "/progress";
  } else {
    url += "cluevo/v1/modules/" + moduleId + "/progress";
  }
  jQuery.ajax({
    url: url,
    method: "POST",
    contentType: "application/json",
    dataType: "json",
    data: JSON.stringify(data),
    beforeSend: function (xhr) {
      xhr.setRequestHeader("X-WP-Nonce", cluevoWpApiSettings.nonce);
    },
    success: function (response) {
      // TODO: Handle succcess
    },
  });
}

async function cluevo_ext_gdocs_get_module(moduleId) {
  let url =
    cluevoWpApiSettings.root + "cluevo/v1/extensions/gdocs/modules/" + moduleId;
  let result = false;
  await jQuery.ajax({
    url: url,
    method: "GET",
    contentType: "application/json",
    dataType: "json",
    beforeSend: function (xhr) {
      xhr.setRequestHeader("X-WP-Nonce", cluevoWpApiSettings.nonce);
    },
    success: function (response) {
      result = response;
    },
  });
  return result;
}
