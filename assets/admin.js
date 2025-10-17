jQuery(document).ready(function($) {
    $(".delete-class").click(function() {
        var classId = $(this).data("class-id");
        if (confirm(scm_ajax.confirm_delete)) {
            $.ajax({
                url: scm_ajax.ajax_url,
                type: "POST",
                data: { action: "scm_delete_class", class_id: classId, nonce: scm_ajax.nonce },
                success: function(response) {
                    if (response.success) location.reload();
                    else alert("Fout: " + response.data);
                }
            });
        }
    });
    $(".remove-from-class").click(function() {
        var userId = $(this).data("user-id");
        if (confirm("Weet je zeker dat je deze leerling uit de klas wilt verwijderen?")) {
            $.ajax({
                url: scm_ajax.ajax_url,
                type: "POST", 
                data: { action: "scm_remove_from_class", user_id: userId, nonce: scm_ajax.nonce },
                success: function(response) {
                    if (response.success) location.reload();
                    else alert("Fout: " + response.data);
                }
            });
        }
    });
});