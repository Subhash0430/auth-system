$(document).ready(function () {

  $('#loginForm').on('submit', function (e) {
    e.preventDefault();

    const email = $('#email').val().trim();
    const password = $('#password').val();

    $('#loginMessage').html('');

    $.ajax({
      url: 'php/login.php',
      type: 'POST',
      data: {
        email: email,
        password: password
      },
      dataType: 'json',
      success: function (response) {
        if (response.status === 'success') {
          $('#loginMessage').html(
            '<div class="alert alert-success">' + response.message + '</div>'
          );

          setTimeout(function () {
            window.location.href = 'profile.html';
          }, 1000);

        } else {
          $('#loginMessage').html(
            '<div class="alert alert-danger">' + response.message + '</div>'
          );
        }
      },
      error: function () {
        $('#loginMessage').html(
          '<div class="alert alert-danger">Something went wrong. Please try again.</div>'
        );
      }
    });

  });

});