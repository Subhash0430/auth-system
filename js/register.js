$(document).ready(function () {


  $('#registerForm').on('submit', function (e) {
    e.preventDefault(); // stop the page from reloading

    // grab values from the form fields
    const username = $('#username').val().trim();
    const email = $('#email').val().trim();
    const password = $('#password').val();

    // clear any old message
    $('#registerMessage').html('');

    // send data to register.php using AJAX (no page reload)
    $.ajax({
      url: 'php/register.php',
      type: 'POST',
      data: {
        username: username,
        email: email,
        password: password
      },
      dataType: 'json',
      success: function (response) {
        if (response.status === 'success') {
          $('#registerMessage').empty().append(
            $('<div>', { class: 'alert alert-success' }).text(response.message)
          );
          // clear the form
          $('#registerForm')[0].reset();

          // redirect to login page after a short delay
          setTimeout(function () {
            window.location.href = 'login.html';
          }, 1500);

        } else {
          $('#registerMessage').empty().append(
            $('<div>', { class: 'alert alert-danger' }).text(response.message)
          );
        }
      },
      error: function () {
        $('#registerMessage').html(
          '<div class="alert alert-danger">Something went wrong. Please try again.</div>'
        );
      }
    });

  });

});