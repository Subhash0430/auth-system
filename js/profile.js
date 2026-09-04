$(document).ready(function () {

  $.ajax({
    url: 'php/profile.php',
    type: 'GET',
    dataType: 'json',
    success: function (response) {
      if (response.status === 'success') {
        $('#profUsername').text(response.data.username);
        $('#profEmail').text(response.data.email);

        $('#profName').text(response.data.name || 'Not set');
        $('#profAge').text(response.data.age || 'Not set');
        $('#profBio').text(response.data.bio || 'Not set');

        if (response.data.name) $('#editName').val(response.data.name);
        if (response.data.age)  $('#editAge').val(response.data.age);
        if (response.data.bio)  $('#editBio').val(response.data.bio);

        $('#profileContent').show();

      } else {
        window.location.href = 'login.html';
      }
    },
    error: function () {
      window.location.href = 'login.html';
    }
  });

  $(document).on('click', '#editProfileBtn', function () {
    $('#editProfileForm').slideToggle();
  });

  $(document).on('submit', '#editProfileForm', function (e) {
    e.preventDefault();

    var name = $('#editName').val().trim();
    var age = $('#editAge').val();
    var bio = $('#editBio').val().trim();

    $('#editMessage').html('');

    $.ajax({
      url: 'php/update_profile.php',
      type: 'POST',
      data: { name: name, age: age, bio: bio },
      dataType: 'json',
      success: function (response) {
        if (response.status === 'success') {
          $('#editMessage').html('<div class="alert alert-success">' + response.message + '</div>');
          $('#profName').text(name || 'Not set');
          $('#profAge').text(age || 'Not set');
          $('#profBio').text(bio || 'Not set');
        } else {
          $('#editMessage').html('<div class="alert alert-danger">' + response.message + '</div>');
        }
      },
      error: function () {
        $('#editMessage').html('<div class="alert alert-danger">Something went wrong.</div>');
      }
    });
  });

  $(document).on('click', '#logoutBtn', function () {
    $.ajax({
      url: 'php/logout.php',
      type: 'POST',
      dataType: 'json',
      success: function () {
        window.location.href = 'login.html';
      }
    });
  });

});