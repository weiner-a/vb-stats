<?php
$db = new mysqli('localhost', 'root', '', 'volleyball_stats');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_player'])) {
    $fn = $db->real_escape_string($_POST['first_name']);
    $ln = $db->real_escape_string($_POST['last_name']);
    $num = (int)$_POST['number'];
    $pos = $db->real_escape_string($_POST['position']);
    
    $checkSql = "SELECT id FROM players WHERE first_name = '$fn' AND last_name = '$ln'";
    $checkResult = $db->query($checkSql);
    
    if ($checkResult->num_rows > 0) {
        $error_message = "Zawodnik '$fn $ln' już istnieje w bazie!";
    } else {
        $insertSql = "INSERT INTO players (first_name, last_name, number, position) VALUES ('$fn', '$ln', $num, '$pos')";
        if ($db->query($insertSql)) {
            $success_message = "Zawodnik '$fn $ln' został dodany pomyślnie!";
        } else {
            $error_message = "Błąd dodawania: " . $db->error;
        }
    }
}

session_start();

// ✅ ZAPIS ZAZNACZEŃ PRZED WYŚLIANIEM DO MATCH.PHP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['players'])) {
    $_SESSION['selected_players'] = $_POST['players'];
}

$selectedPlayers = $_SESSION['selected_players'] ?? [];

function getPlayersHtml($db, $filter, $selectedPlayers) {
    $sql = "SELECT * FROM players";
    if ($filter !== 'ALL') {
        $safeFilter = $db->real_escape_string($filter);
        $sql .= " WHERE position = '$safeFilter'";
    }
    $sql .= " ORDER BY number";

    $result = $db->query($sql);
    $html = '';
    while ($p = $result->fetch_assoc()) {
        $name = htmlspecialchars($p['first_name'] . " " . $p['last_name']);
        $pos = htmlspecialchars($p['position']);
        $num = (int)$p['number'];
        $id = (int)$p['id'];
        $isChecked = in_array($id, $selectedPlayers) ? 'checked' : '';
        
        $html .= '<label>
            <input class="player-checkbox" type="checkbox" name="players[]" value="' . $id . '" data-position="' . $pos . '" data-name="' . $name . '" data-number="' . $num . '" ' . $isChecked . '>
            <span class="player-info"><strong>' . $name . '</strong><br><span class="player-position">#' . $num . ' • ' . ucfirst($pos) . '</span></span>
        </label>';
    }
    return $html;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    $filter = $_GET['filter'] ?? 'ALL';
    echo getPlayersHtml($db, $filter, $selectedPlayers);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Index - Zawodnicy</title>
  <link rel="stylesheet" href="style.css">

  <style>
    /* Wszystkie style bez zmian */
    .container {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 20px;
    }
    .form-container {
      width: 45%;
      flex-shrink: 0;
      display: flex;
      flex-direction: column;
    }
    .message {
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 15px;
      font-weight: 500;
      animation: slideIn 0.3s ease;
    }
    .success-message {
      background: linear-gradient(135deg, #2ecc71, #27ae60);
      color: white;
      border: 1px solid #27ae60;
    }
    .error-message {
      background: linear-gradient(135deg, #e74c3c, #c0392b);
      color: white;
      border: 1px solid #c0392b;
    }
    @keyframes slideIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .selected-players-section {
      margin-top: 20px;
      padding: 15px;
      background: linear-gradient(135deg, #2ecc71, #27ae60);
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
      transition: all 0.3s ease;
    }
    .selected-players-section.error {
      background: linear-gradient(135deg, #e74c3c, #c0392b);
      box-shadow: 0 4px 15px rgba(231, 76, 60, 0.5);
      animation: shake 0.5s ease-in-out;
    }
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      25% { transform: translateX(-5px); }
      75% { transform: translateX(5px); }
    }
    .selected-players-section h3 {
      color: white;
      margin: 0 0 15px 0;
      font-size: 18px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .validation-error {
      background: rgba(255,255,255,0.2);
      border: 1px solid rgba(255,255,255,0.3);
      border-radius: 6px;
      padding: 10px;
      margin-top: 10px;
      color: white;
      font-weight: 500;
      font-size: 14px;
      animation: fadeIn 0.3s ease;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .selected-players-list {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      max-height: 165px;
      overflow-y: auto;
    }
    .selected-player {
      background: rgba(255,255,255,0.9);
      padding: 8px 12px;
      border-radius: 20px;
      font-weight: 500;
      font-size: 14px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .selected-player .position-tag {
      font-size: 12px;
      padding: 2px 6px;
      border-radius: 10px;
      color: white;
      font-weight: bold;
    }
    .list-container {
      width: 50%;
      display: flex;
      flex-direction: column;
    }
    .list-container h2 {
      margin-top: 0;
    }
    .filter-buttons {
      display: flex;
      gap: 8px;
      margin-bottom: 15px;
      flex-wrap: wrap;
    }
    .filter-btn {
      padding: 8px 16px;
      border: 2px solid #3498db;
      background: white;
      color: #3498db;
      border-radius: 20px;
      cursor: pointer;
      font-weight: 500;
      font-size: 14px;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .filter-btn:hover {
      background: #3498db;
      color: white;
      transform: translateY(-1px);
    }
    .filter-btn.active {
      background: linear-gradient(135deg, #e74c3c, #c0392b);
      color: white;
      border-color: #e74c3c;
      box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
    }
    .filter-btn.active:hover {
      background: linear-gradient(135deg, #c0392b, #a93226);
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(231, 76, 60, 0.4);
    }
    .players-scrollable {
      max-height: 329px;
      overflow-y: auto;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      padding: 15px;
      background: #fafafa;
      margin: 15px 0;
      box-shadow: inset 0 2px 8px rgba(0,0,0,0.1);
    }
    .players-scrollable::-webkit-scrollbar {
      width: 12px;
    }
    .players-scrollable::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 10px;
    }
    .players-scrollable::-webkit-scrollbar-thumb {
      background: linear-gradient(180deg, #3498db, #2980b9);
      border-radius: 10px;
      border: 2px solid #f1f1f1;
    }
    .players-scrollable::-webkit-scrollbar-thumb:hover {
      background: linear-gradient(180deg, #2980b9, #1f6391);
    }
    .players-scrollable {
      scrollbar-width: thin;
      scrollbar-color: #3498db #f1f1f1;
    }
    .list-container label {
      display: flex;
      align-items: center;
      cursor: pointer;
      margin-bottom: 12px;
      padding: 8px 12px;
      background: white;
      border-radius: 6px;
      transition: all 0.2s ease;
      border-left: 4px solid #ecf0f1;
      position: relative;
    }
    .list-container label:hover {
      background: #e8f4f8;
      border-left-color: #3498db;
      transform: translateX(2px);
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .list-container input[type="checkbox"] {
      margin-right: 12px;
      transform: scale(1.3);
      accent-color: #3498db;
      cursor: pointer;
    }
    .player-info {
      flex: 1;
      font-size: 15px;
    }
    .player-position {
      font-size: 13px;
      color: #7f8c8d;
      font-weight: 500;
    }
    button.start-match-button {
      margin-top: 15px;
      padding: 12px 30px;
      font-size: 1.1em;
      background: linear-gradient(135deg, #27ae60, #2ecc71);
      color: white;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: bold;
      box-shadow: 0 4px 15px rgba(39,174,96,0.4);
      transition: all 0.3s ease;
    }
    button.start-match-button:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(39,174,96,0.6);
    }
    button.start-match-button:active {
      transform: translateY(0);
    }
    @media (max-width: 768px) {
      .container {
        flex-direction: column;
      }
      .form-container, .list-container {
        width: 100%;
      }
      .filter-buttons {
        justify-content: center;
      }
    }
    #playerSearch {
      width: 100%;
      padding: 8px 12px;
      font-size: 16px;
      border-radius: 6px;
      border: 1px solid #ccc;
      margin-bottom: 10px;
      box-sizing: border-box;
    }
  </style>
</head>
<body>

<div class="container">

  <div class="form-container">
    <h2>Dodaj zawodnika</h2>
    
    <?php if (isset($success_message)): ?>
      <div class="message success-message"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if (isset($error_message)): ?>
      <div class="message error-message"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form method="post">
      <label>Imię:<br><input name="first_name" required style="width:100%;padding:8px;margin:5px 0;border-radius:4px;border:1px solid #ddd;"></label><br>
      <label>Nazwisko:<br><input name="last_name" required style="width:100%;padding:8px;margin:5px 0;border-radius:4px;border:1px solid #ddd;"></label><br>
      <label>Numer:<br><input type="number" name="number" required style="width:100%;padding:8px;margin:5px 0;border-radius:4px;border:1px solid #ddd;"></label><br>
      <label>Pozycja:<br>
        <select name="position" required style="width:100%;padding:8px;margin:5px 0;border-radius:4px;border:1px solid #ddd;">
          <option value="libero">Libero</option>
          <option value="rozgrywający">Rozgrywający</option>
          <option value="atakujący">Atakujący</option>
          <option value="Środkowy">Środkowy</option>
          <option value="przyjmujący">Przyjmujący</option>
        </select>
      </label><br>
      <button type="submit" name="add_player" style="width:100%;padding:12px;background:#3498db;color:white;border:none;border-radius:6px;font-weight:bold;cursor:pointer;">Dodaj zawodnika</button>
    </form>

    <div class="selected-players-section" id="selectedPlayersSection" style="display: none;">
      <h3>Zawodnicy wybrani do gry (<span id="selectedCount">0</span>)</h3>
      <div class="selected-players-list" id="selectedPlayersList"></div>
      <div id="validationError" class="validation-error" style="display: none;"></div>
    </div>
  </div>

  <div class="list-container">
    <h2>Wybierz zawodników do śledzenia</h2>

    <div class="filter-buttons">
      <button class="filter-btn active" data-position="ALL">ALL</button>
      <button class="filter-btn" data-position="rozgrywający">S</button>
      <button class="filter-btn" data-position="atakujący">OP</button>
      <button class="filter-btn" data-position="przyjmujący">OH</button>
      <button class="filter-btn" data-position="Środkowy">MB</button>
      <button class="filter-btn" data-position="libero">L</button>
    </div>

    <input type="text" id="playerSearch" onkeyup="filterPlayers()" placeholder="Wyszukaj zawodnika..." title="Wyszukaj zawodnika">
    <button type="button" onclick="toggleAllCheckboxes()" style="padding:8px 16px;background:#95a5a6;color:white;border:none;border-radius:4px;cursor:pointer;margin-bottom:10px;">Zaznacz/Odznacz wszystkich</button>

    <!-- ✅ UKRYTE POLA Z WSZYSTKIMI ZAZNACZONYMI ZAWODNIKAMI -->
    <form action="match.php" method="post" id="matchForm" onsubmit="saveAllCheckedPlayers(); return validateForm();">
      <input type="hidden" name="all_selected_players" id="allSelectedPlayers" value="">
      <div class="players-scrollable" id="playersList">
        <?php echo getPlayersHtml($db, 'ALL', $selectedPlayers); ?>
      </div>
      <button class="start-match-button" type="submit">Rozpocznij mecz</button>
    </form>
  </div>
</div>

<script>
  const buttons = document.querySelectorAll('.filter-btn');
  const playersListContainer = document.getElementById('playersList');
  const playerSearchInput = document.getElementById('playerSearch');
  const selectedPlayersSection = document.getElementById('selectedPlayersSection');
  const selectedPlayersList = document.getElementById('selectedPlayersList');
  const selectedCount = document.getElementById('selectedCount');
  const validationError = document.getElementById('validationError');
  const allSelectedPlayersInput = document.getElementById('allSelectedPlayers');

  let checkedPlayerMap = new Map();

  // ✅ KLUCZOWA FUNKCJA - ZAPISUJE WSZYSTKIE ZAZNACZONE PRZED WYŚLIANIEM
  function saveAllCheckedPlayers() {
    const selectedIds = Array.from(checkedPlayerMap.entries())
      .filter(([id, data]) => data.checked)
      .map(([id]) => id);
    allSelectedPlayersInput.value = JSON.stringify(selectedIds);
    return true;
  }

  function updateCheckedPlayersMap() {
    document.querySelectorAll('.player-checkbox').forEach(cb => {
      checkedPlayerMap.set(cb.value, {
        checked: cb.checked,
        name: cb.dataset.name,
        number: cb.dataset.number,
        position: cb.dataset.position
      });
    });
  }

  function restoreCheckedPlayers() {
    document.querySelectorAll('.player-checkbox').forEach(cb => {
      const data = checkedPlayerMap.get(cb.value);
      if (data && data.checked) {
        cb.checked = true;
      }
    });
    updateSelectedPlayersDisplay();
  }

  function updateSelectedPlayersDisplay() {
    let count = 0;
    selectedPlayersList.innerHTML = '';

    checkedPlayerMap.forEach((data, id) => {
      if (data.checked) {
        count++;
        const playerDiv = document.createElement('div');
        playerDiv.className = 'selected-player';
        playerDiv.innerHTML = `
          #${data.number} ${data.name}
          <span class="position-tag" style="background: ${getPositionColor(data.position)}">${getPositionShort(data.position)}</span>
        `;
        selectedPlayersList.appendChild(playerDiv);
      }
    });

    selectedCount.textContent = count;
    selectedPlayersSection.style.display = count > 0 ? 'block' : 'none';
    validateAndHighlight();
  }

  function validateAndHighlight() {
    let count = 0;
    let liberoCount = 0;

    checkedPlayerMap.forEach((data) => {
      if (data.checked) {
        count++;
        if (data.position === 'libero') liberoCount++;
      }
    });

    const errorDiv = validationError;
    let hasError = false;

    selectedPlayersSection.classList.remove('error');
    errorDiv.style.display = 'none';

    if (count < 6) {
      selectedPlayersSection.classList.add('error');
      errorDiv.textContent = `Za mało zawodników! Potrzebujesz minimum 6 (masz: ${count})`;
      errorDiv.style.display = 'block';
      hasError = true;
    } else if (count > 14) {
      selectedPlayersSection.classList.add('error');
      errorDiv.textContent = `Za dużo zawodników! Maksymalnie 14 (masz: ${count})`;
      errorDiv.style.display = 'block';
      hasError = true;
    } else if (liberoCount > 2) {
      selectedPlayersSection.classList.add('error');
      errorDiv.textContent = `Za dużo libero! Maksymalnie 2 (masz: ${liberoCount})`;
      errorDiv.style.display = 'block';
      hasError = true;
    } else if ((count >= 12 && count <= 14) && liberoCount < 2) {
      selectedPlayersSection.classList.add('error');
      errorDiv.textContent = `Przy 12-14 zawodnikach potrzebujesz 2 libero (masz: ${liberoCount})`;
      errorDiv.style.display = 'block';
      hasError = true;
    }

    return !hasError;
  }

  function getPositionColor(position) {
    const colors = {
      'rozgrywający': '#3498db',
      'atakujący': '#e74c3c',
      'przyjmujący': '#f39c12',
      'środkowy': '#27ae60',
      'libero': '#9b59b6'
    };
    return colors[position] || '#95a5a6';
  }

  function getPositionShort(position) {
    const shorts = {
      'rozgrywający': 'S',
      'atakujący': 'OP',
      'przyjmujący': 'OH',
      'środkowy': 'MB',
      'libero': 'L'
    };
    return shorts[position] || position.charAt(0).toUpperCase();
  }

  document.addEventListener('change', e => {
    if (e.target.classList.contains('player-checkbox')) {
      updateCheckedPlayersMap();
      updateSelectedPlayersDisplay();
    }
  });

  function filterPlayers() {
    const filter = playerSearchInput.value.toLowerCase();
    const labels = playersListContainer.getElementsByTagName('label');
    for (let label of labels) {
      const text = label.textContent || label.innerText;
      label.style.display = text.toLowerCase().includes(filter) ? '' : 'none';
    }
  }

  function toggleAllCheckboxes() {
    const checkboxes = playersListContainer.querySelectorAll('.player-checkbox');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
    updateCheckedPlayersMap();
    updateSelectedPlayersDisplay();
  }

  window.toggleAllCheckboxes = toggleAllCheckboxes;
  window.filterPlayers = filterPlayers;

  buttons.forEach(btn => {
    btn.addEventListener('click', () => {
      updateCheckedPlayersMap();
      buttons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const position = btn.getAttribute('data-position');
      fetch(`?ajax=1&filter=${encodeURIComponent(position)}`)
        .then(res => res.text())
        .then(html => {
          playersListContainer.innerHTML = html;
          restoreCheckedPlayers();
          filterPlayers();
        })
        .catch(err => console.error('Błąd ładowania zawodników:', err));
    });
  });

  document.addEventListener('DOMContentLoaded', () => {
    updateCheckedPlayersMap();
    updateSelectedPlayersDisplay();
  });

  function validateForm() {
    const isValid = validateAndHighlight();
    if (!isValid) {
      const errorMsg = validationError.textContent;
      alert(errorMsg);
      return false;
    }
    return saveAllCheckedPlayers(); // ✅ ZAPISUJ PRZED WYŚLIANIEM
  }
  window.validateForm = validateForm;
</script>

</body>
</html>
