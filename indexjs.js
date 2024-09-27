// indexjs.js

$(document).ready(function() {
    // Show dropdown menu on hover
    $('.dropdown').hover(function() {
        $(this).find('.dropdown-menu').stop(true, true).delay(100).fadeIn(200);
    }, function() {
        $(this).find('.dropdown-menu').stop(true, true).delay(100).fadeOut(200);
    });
     // Show dropdown menu on hover
     $('.nav-item.dropdown').hover(function() {
        $(this).find('.dropdown-menu').stop(true, true).delay(100).fadeIn(200);
    }, function() {
        $(this).find('.dropdown-menu').stop(true, true).delay(100).fadeOut(200);
    });

    // AJAX search functionality
    $(document).ready(function() {
        $('#search-input').on('input', function() {
            var searchTerm = $(this).val();
            if (searchTerm.length > 2) {
                $.ajax({
                    url: 'index.php',
                    method: 'GET',
                    data: { search: searchTerm },
                    dataType: 'json',
                    success: function(response) {
                        var resultsHtml = '';
                        if (response.length > 0) {
                            response.forEach(function(item) {
                                resultsHtml += '<div class="search-item">';
                                resultsHtml += '<a href="recipe.php?id=' + item.MealID + '&type=' + item.meal_type + '">';
                                resultsHtml += item.MealName;
                                resultsHtml += '</a></div>';
                            });
                        } else {
                            resultsHtml = '<div class="no-results">No results found</div>';
                        }
                        $('#search-results').html(resultsHtml).show();
                    },
                    error: function() {
                        $('#search-results').html('<div class="error">Error occurred during search</div>').show();
                    }
                });
            } else {
                $('#search-results').hide();
            }
        });
    
        // Hide search results when clicking outside
        $(document).on('click', function(event) {
            if (!$(event.target).closest('.search-container').length) {
                $('#search-results').hide();
            }
        });
    });
});
