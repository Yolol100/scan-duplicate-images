jQuery(document).ready(($) => {
  // Maak de huidige pagina en updatePagination globaal beschikbaar.
  window.currentPage = 1;
  window.updatePagination = function() {
    const rowsPerPage = parseInt($('#rowsPerPage').val(), 10);
    const $filteredRows = $('.unique-tbody tr.filtered-match');
    const totalRows = $filteredRows.length;
    const totalPages = Math.ceil(totalRows / rowsPerPage) || 1;

    $('#totalPages, #totalPagesBottom').text(totalPages);

    // Verberg alle rijen en toon alleen de gefilterde rijen voor de huidige pagina.
    $('.unique-tbody tr').hide();
    const startIndex = (window.currentPage - 1) * rowsPerPage;
    $filteredRows.slice(startIndex, startIndex + rowsPerPage).show();

    $('#currentPage, #currentPageBottom').text(window.currentPage);

    $('#prevPage, #prevPageBottom').prop('disabled', window.currentPage === 1);
    $('#nextPage, #nextPageBottom').prop('disabled', window.currentPage === totalPages);

    // Toon de "Geen resultaten"-melding als er geen resultaten zijn.
    if ($filteredRows.length === 0) {
      document.getElementById("noResults").style.display = "block";
    } else {
      document.getElementById("noResults").style.display = "none";
    }
  };

  // Aangepaste zoekfunctie: als er geen invoer is, voeg 'filtered-match' toe aan alle rijen.
  window.filterTable = function() {
    const inputHeader = document.getElementById("search-input") ? document.getElementById("search-input").value : "";
    const inputFooter = document.getElementById("search-input-bottom") ? document.getElementById("search-input-bottom").value : "";
    const input = (inputHeader || inputFooter).trim().toLowerCase();
    const rows = document.querySelectorAll(".unique-tbody tr");
    let resultsFound = false; // Variabele om te controleren of er resultaten zijn

    // Filteren op basis van de huidige selectie van de filter
    const selectedFilter = $('#pageFilter').val() || $('#pageFilterBottom').val();
    const $filteredRows = $('.unique-tbody tr').filter(function() {
      const status = $(this).data('status');
      return selectedFilter === 'all' || selectedFilter === status;
    });

    if (input === "") {
      // Als de zoekbalk leeg is, markeer alle rijen als match.
      $filteredRows.each(function() {
        $(this).addClass("filtered-match");
      });
      document.getElementById("noResults").style.display = "none"; // Verberg de geen resultaten melding
    } else {
      $filteredRows.each(function() {
        const bestandsnaam = $(this).find('.filename-input').val() || '';
        const nieuweBestandsnaam = $(this).find('.new-filename-input').val() || '';
        const pagina = $(this).find('.unique-td-pagina').text() || '';
        const titel = $(this).find('.title-input').val() || '';
        const caption = $(this).find('.caption-input').val() || '';
        const description = $(this).find('.description-input').val() || '';
        const alt = $(this).find('.alt-input').val() || '';
        
        const rowText = (bestandsnaam + " " + nieuweBestandsnaam + " " + pagina + " " + titel + " " + caption + " " + description + " " + alt).toLowerCase();
        
        if (rowText.indexOf(input) > -1) {
          $(this).addClass("filtered-match");
          resultsFound = true; // Er is minstens één match
        } else {
          $(this).removeClass("filtered-match");
        }
      });

      // Toon of verberg de "Geen resultaten"-melding
      if (resultsFound) {
        document.getElementById("noResults").style.display = "none";
      } else {
        document.getElementById("noResults").style.display = "block";
      }
    }
    window.currentPage = 1;
    window.updatePagination();
  };

  // Functie die de rijen markeert op basis van de dropdown-filter(s).
  const applyFilterFunction = (filterSelector) => {
    const selectedFilter = $(filterSelector).val();
    $('.unique-tbody tr').each(function () {
      const $row = $(this);
      const status = $row.data('status');
      if (selectedFilter === 'all' || selectedFilter === status) {
        $row.addClass('filtered-match');
      } else {
        $row.removeClass('filtered-match');
      }
    });
    
    // Reset de zoekbalken bij filterwisseling
    $('#search-input').val('');
    $('#search-input-bottom').val('');
    document.getElementById("noResults").style.display = "none"; // Verberg de "Geen resultaten" melding
    window.currentPage = 1;
    window.updatePagination();
  };

  // Filteren op de bovenste filterbalk.
  $('#applyFilter').on('click', () => {
    applyFilterFunction('#pageFilter');
  });

  // Filteren op de onderste filterbalk.
  $('#applyFilterBottom').on('click', () => {
    applyFilterFunction('#pageFilterBottom');
  });

  // Extra filtering op specifieke tekstvelden (indien gewenst).
  $('#apply-filters').on('click', () => {
    const titleFilter = $('#filter-title').val().toLowerCase();
    const captionFilter = $('#filter-caption').val().toLowerCase();
    const altFilter = $('#filter-alt').val().toLowerCase();

    $('.unique-tbody tr').each(function () {
      const $row = $(this);
      const title = $row.find('.title-input').val().toLowerCase();
      const caption = $row.find('.caption-input').val().toLowerCase();
      const alt = $row.find('.alt-input').val().toLowerCase();

      if (
        (!titleFilter || title.includes(titleFilter)) &&
        (!captionFilter || caption.includes(captionFilter)) &&
        (!altFilter || alt.includes(altFilter))
      ) {
        $row.addClass('filtered-match');
      } else {
        $row.removeClass('filtered-match');
      }
    });
    window.currentPage = 1;
    window.updatePagination();
  });

  // Filters wissen
  $('#clearFilter, #clear-filters').on('click', () => {
    $('#pageFilter, #pageFilterBottom').val('all');
    $('#filter-title, #filter-caption, #filter-alt').val('');
    $('#search-input').val('');
    $('#search-input-bottom').val('');
    $('.unique-tbody tr').addClass('filtered-match').show();
    window.currentPage = 1;
    window.updatePagination();
  });

  // Synchroniseer de "Rijen per pagina" select in header en footer.
  $('#rowsPerPage, #rowsPerPageBottom').on('change', function () {
    const rowsPerPage = $(this).val();
    $('#rowsPerPage, #rowsPerPageBottom').val(rowsPerPage);
    window.currentPage = 1;
    window.updatePagination();
  });

  // AJAX voor opslaan van metadata met aangepaste succesmelding
  $('.save-meta').off('click').on('click', function () {
    const $button = $(this);
    const $row = $button.closest('tr');
    $row.removeClass('success error');

    const attachmentId = $button.data('attachment-id');
    const newTitle = $row.find('.title-input').val();
    const newCaption = $row.find('.caption-input').val();
    const newDescription = $row.find('.description-input').val();
    const newAlt = $row.find('.alt-input').val();
    const newFilename = $row.find('.new-filename-input').val();

    if (typeof modernImageManagerAjax === 'undefined' || !modernImageManagerAjax.ajax_url) {
      alert('AJAX URL is niet beschikbaar!');
      return;
    }

    $button.prop('disabled', true);

    $.ajax({
      url: modernImageManagerAjax.ajax_url,
      method: 'POST',
      data: {
        action: 'update_media_meta',
        attachment_id: attachmentId,
        title: newTitle,
        caption: newCaption,
        description: newDescription,
        alt: newAlt,
        new_filename: newFilename,
      },
      success: (response) => {
        $button.prop('disabled', false);
        if (response.success) {
          $row.addClass('success');
          setTimeout(() => $row.removeClass('success'), 2000);
          // Update de bestandsnaam in de UI als er een nieuwe is opgegeven.
          if (newFilename) {
            $row.find('.filename-input').val(newFilename);
          }
          const successMessage = response.data && response.data.message ? response.data.message : "Actie succesvol uitgevoerd.";
          alert("Succes: " + successMessage);
        } else {
          $row.addClass('error');
          setTimeout(() => $row.removeClass('error'), 2000);
          const message =
            response.data && response.data.message
              ? `Fout: ${response.data.message}`
              : 'Er is een onbekende fout opgetreden.';
          alert(message);
        }
      },
      error: (xhr, status, error) => {
        console.error('AJAX Error:', status, error);
        $button.prop('disabled', false);
        $row.addClass('error');
        setTimeout(() => $row.removeClass('error'), 2000);
        alert('Er is een fout opgetreden bij het verzenden van het verzoek.');
      },
    });
  });

  // AJAX voor verwijderen van media (ongewijzigd)
  $('.delete-media').off('click').on('click', function () {
    if (!confirm('Weet u zeker dat u deze media wilt verwijderen?')) {
      return;
    }
    const $button = $(this);
    const $row = $button.closest('tr');
    const attachmentId = $button.data('attachment-id');

    if (typeof modernImageManagerAjax === 'undefined' || !modernImageManagerAjax.ajax_url) {
      alert('AJAX URL is niet beschikbaar!');
      return;
    }

    $button.prop('disabled', true);

    $.ajax({
      url: modernImageManagerAjax.ajax_url,
      method: 'POST',
      data: {
        action: 'delete_media',
        attachment_id: attachmentId,
      },
      success: (response) => {
        if (response.success) {
          $row.fadeOut(500, function () {
            $(this).remove();
            window.updatePagination();
          });
        } else {
          alert(
            response.data && response.data.message
              ? `Fout: ${response.data.message}`
              : 'Er is een onbekende fout opgetreden bij het verwijderen.'
          );
          $button.prop('disabled', false);
        }
      },
      error: (xhr, status, error) => {
        console.error('AJAX Error:', status, error);
        alert('Er is een fout opgetreden bij het verwijderen.');
        $button.prop('disabled', false);
      },
    });
  });

  // Evenementen voor paginering (bovenste balk)
  $('#nextPage').on('click', () => {
    window.currentPage++;
    window.updatePagination();
  });
  $('#prevPage').on('click', () => {
    window.currentPage--;
    window.updatePagination();
  });

  // Evenementen voor paginering (onderste balk)
  $('#nextPageBottom').on('click', () => {
    window.currentPage++;
    window.updatePagination();
  });
  $('#prevPageBottom').on('click', () => {
    window.currentPage--;
    window.updatePagination();
  });

  // Begin met alle rijen als match (geen filter) en initialiseer de paginering.
  $('.unique-tbody tr').addClass('filtered-match');
  window.updatePagination();
});