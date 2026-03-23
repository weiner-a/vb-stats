<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['all_selected_players'])) {
    $selectedPlayers = json_decode($_POST['all_selected_players'], true);
    
    if (!$selectedPlayers || empty($selectedPlayers)) {
        die("Brak wybranych zawodników.");
    }
} else {
    die("Brak wybranych zawodników.");
}

$placeholders = implode(',', array_fill(0, count($selectedPlayers), '?'));
$stmt = $db->prepare("SELECT * FROM players WHERE id IN ($placeholders)");
$stmt->bind_param(str_repeat('i', count($selectedPlayers)), ...$selectedPlayers);
$stmt->execute();
$players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function canServe($pos) { return $pos !== 'libero'; }
function canSet($pos) { return $pos === 'rozgrywający'; }
function canBlock($pos) { return $pos !== 'libero'; }
function canAttack($pos) { return $pos !== 'libero'; }

$liberos = [];
foreach ($players as $p) {
    if ($p['position'] === 'libero' && in_array($p['id'], $selectedPlayers)) {
        $liberos[] = $p['id'];
    }
}

$db->query("INSERT INTO matches (match_date) VALUES (CURDATE())");
$match_id = $db->insert_id;
?>

<!DOCTYPE html>
<html>
<head>
<title>Match - Statystyki</title>
<link rel="stylesheet" href="style.css">
<script>
let presentPlayers = new Set();
let playerPositions = new Map();
let notificationCounter = 0;

<?php foreach ($players as $p): ?>
playerPositions.set(<?php echo $p['id']; ?>, '<?php echo addslashes($p['position']); ?>');
<?php endforeach; ?>

function showTemporaryMessage(message) {
    notificationCounter++;
    const msgId = 'notification-' + notificationCounter;
    
    let msgDiv = document.createElement('div');
    msgDiv.id = msgId;
    msgDiv.textContent = message;
    msgDiv.style.position = 'fixed';
    msgDiv.style.bottom = (20 + (notificationCounter * 60)) + 'px'; // stack vertically
    msgDiv.style.right = '20px';
    msgDiv.style.backgroundColor = '#4BB543';
    msgDiv.style.color = 'white';
    msgDiv.style.padding = '10px 15px';
    msgDiv.style.borderRadius = '5px';
    msgDiv.style.boxShadow = '0 2px 6px rgba(0,0,0,0.3)';
    msgDiv.style.zIndex = 10000 + notificationCounter;
    msgDiv.style.fontSize = '0.9em';
    msgDiv.style.opacity = '0';
    msgDiv.style.transition = 'opacity 0.3s ease-in-out, bottom 0.3s ease-in-out';
    msgDiv.style.maxWidth = '300px';
    msgDiv.style.wordWrap = 'break-word';
    
    document.body.appendChild(msgDiv);
    
    // Fade in
    requestAnimationFrame(() => {
        msgDiv.style.opacity = '1';
    });
    
    // Remove after 1 second with fade out
    setTimeout(() => {
        msgDiv.style.opacity = '0';
        msgDiv.style.bottom = (20 + ((notificationCounter - 1) * 60)) + 'px'; // move up to fill gap
        
        setTimeout(() => {
            if (document.body.contains(msgDiv)) {
                document.body.removeChild(msgDiv);
            }
            // Update counter for next notification to use correct position
            notificationCounter--;
        }, 300);
    }, 1000);
}

function addStat(playerId, statType, value) {
  fetch('update_stat.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({match_id: <?php echo $match_id; ?>, player_id: playerId, stat: statType, value: value})
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showTemporaryMessage('Dodano statystykę!');
    }
    else alert('Błąd podczas dodawania statystyki');
  })
  .catch(() => alert('Błąd sieci'));
}

function togglePresent(playerId) {
  if (presentPlayers.has(playerId)) presentPlayers.delete(playerId);
  else presentPlayers.add(playerId);
  updatePresentDisplay();
}

function updatePresentDisplay() {
  const container = document.getElementById('present-players');
  container.innerHTML = '';

  const groups = {};
  presentPlayers.forEach(id => {
    const pos = playerPositions.get(id);
    if (!groups[pos]) groups[pos] = [];
    const label = document.querySelector('label[for="present-' + id + '"]');
    if (label) {
      groups[pos].push(label.cloneNode(true));
    }
  });

  for (const pos in groups) {
    const grpDiv = document.createElement('div');
    grpDiv.style.marginBottom = '10px';
    const title = document.createElement('strong');
    title.textContent = pos.charAt(0).toUpperCase() + pos.slice(1);
    grpDiv.appendChild(title);

    groups[pos].forEach(labelNode => {
      grpDiv.appendChild(document.createTextNode(' '));
      grpDiv.appendChild(labelNode);

      if(pos === 'libero' && labelNode.htmlFor) {
        const currentLiberoId = parseInt(labelNode.htmlFor.replace('present-', ''));
        <?php if(count($liberos) === 2): ?>
          const liberoIds = <?php echo json_encode($liberos); ?>;
          const otherLiberoId = liberoIds.find(id => id !== currentLiberoId);
          const btn = document.createElement('button');
          btn.textContent = 'Zamień';
          btn.style.marginLeft = '5px';
          btn.style.fontSize = '0.8em';
          btn.style.backgroundColor ='#219150';
          btn.onclick = function(e) {
            e.stopPropagation();
            swapLibero(currentLiberoId, otherLiberoId);
          };
          labelNode.parentNode.insertBefore(btn, labelNode.nextSibling);
        <?php endif; ?>
      }
    });
    container.appendChild(grpDiv);
  }
}

function swapLibero(id1, id2) {
  let cb1 = document.getElementById('present-' + id1);
  let cb2 = document.getElementById('present-' + id2);
  if(cb1 && cb2) {
    cb1.checked = false;
    cb1.dispatchEvent(new Event('change'));
    cb2.checked = true;
    cb2.dispatchEvent(new Event('change'));
  }
}

function toggleCheckbox(checkboxId) {
  const cb = document.getElementById(checkboxId);
  if (cb) {
    cb.checked = !cb.checked;
    cb.dispatchEvent(new Event('change'));
  }
}

function changePosition(playerId) {
  const currentPos = playerPositions.get(playerId);
  const select = document.getElementById('pos-select-' + playerId);
  const newPos = select.value;
  
  if (newPos !== currentPos) {
    playerPositions.set(playerId, newPos);
    
    const label = document.querySelector('label[for="present-' + playerId + '"]');
    if (label) {
      label.setAttribute('data-position', newPos);
    }
    
    updatePresentDisplay();
    
    localStorage.setItem('playerPositions_' + <?php echo $match_id; ?>, JSON.stringify(Array.from(playerPositions)));
    
    alert('Pozycja zmieniona na: ' + newPos);
  }
}

function previewStats() {
  window.open('summary.php?match_id=<?php echo $match_id; ?>&preview=1', '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
}

function finishMatch() {
  window.location.href = 'summary.php?match_id=' + <?php echo $match_id; ?>;
}

window.onload = () => {
  const savedPositions = localStorage.getItem('playerPositions_' + <?php echo $match_id; ?>);
  if (savedPositions) {
    const parsed = JSON.parse(savedPositions);
    parsed.forEach(([id, pos]) => playerPositions.set(id, pos));
  }
  updatePresentDisplay();
};
</script>
<style>
#present-players div strong {
  color: #2c3e50;
  font-size: 1.1em;
}
#present-players label {
  background-color: #3498db;
  color: white;
  padding: 3px 8px;
  margin-right: 5px;
  border-radius: 4px;
  display: inline-block;
  cursor: default;
}
#present-players button {
  cursor: pointer;
}
td.checkbox-cell {
  text-align:center;
  cursor:pointer;
}

button.finish-match-btn, .preview-stats-btn {
  margin-top: 20px;
  padding: 10px 20px;
  color: white;
  border: none;
  border-radius: 5px;
  font-size: 1em;
  cursor: pointer;
  margin-right: 10px;
}
button.finish-match-btn {
  background-color: #27ae60;
}
button.finish-match-btn:hover {
  background-color: #219150;
}
.preview-stats-btn {
  background-color: #3498db;
}
.preview-stats-btn:hover {
  background-color: #2980b9;
}

.position-controls {
  margin-top: 15px;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 8px;
  border-left: 4px solid #ffc107;
}
.position-select {
  padding: 5px 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  margin-left: 10px;
  min-width: 120px;
}
</style>
</head>
<body>

<h2>Zawodnicy obecni na boisku:</h2>
<div id="present-players"></div>

<h2>Ocena statystyk meczu</h2>
<table>
<tr>
<th>Obecny<br>na boisku</th><th>Zawodnik (Nr)</th><th>Pozycja</th><th>Przyjęcie</th><th>Atak</th><th>Rozegranie</th><th>Obrona</th><th>Błąd własny</th><th>Zagrywka</th><th>Blok</th><th>Wyblok</th>
</tr>

<?php foreach ($players as $p):
  $pid = $p['id'];
  $position = htmlspecialchars($p['position']);
?>
<tr>
<td class="checkbox-cell" onclick="toggleCheckbox('present-<?php echo $pid; ?>')">
  <input type="checkbox" id="present-<?php echo $pid; ?>" style="pointer-events:none;" onchange="togglePresent(<?php echo $pid; ?>)">
</td>

<td>
  <label for="present-<?php echo $pid; ?>" data-position="<?php echo $position; ?>">
    <?php echo htmlspecialchars("{$p['first_name']} {$p['last_name']} (#{$p['number']})"); ?>
  </label>
</td>

<td>
  <select id="pos-select-<?php echo $pid; ?>" class="position-select" onchange="changePosition(<?php echo $pid; ?>)">
    <option value="atakujący" <?php echo $position === 'atakujący' ? 'selected' : ''; ?>>Atakujący</option>
    <option value="środkowy" <?php echo $position === 'środkowy' ? 'selected' : ''; ?>>Środkowy</option>
    <option value="rozgrywający" <?php echo $position === 'rozgrywający' ? 'selected' : ''; ?>>Rozgrywający</option>
    <option value="przyjmujący" <?php echo $position === 'przyjmujący' ? 'selected' : ''; ?>>Przyjmujący</option>
    <option value="libero" <?php echo $position === 'libero' ? 'selected' : ''; ?>>Libero</option>
  </select>
</td>

<td>
  <button onclick="addStat(<?php echo $pid; ?>,'reception','perfekcyjne')">Perfekcyjne</button>
  <button onclick="addStat(<?php echo $pid; ?>,'reception','pozytywne')">Pozytywne</button>
  <button onclick="addStat(<?php echo $pid; ?>,'reception','neutralne')">Neutralne</button>
  <button onclick="addStat(<?php echo $pid; ?>,'reception','negatywne')">Negatywne</button>
  <button onclick="addStat(<?php echo $pid; ?>,'reception','dostal_asa')">Dostał asa</button>
</td>

<td>
<?php if (canAttack($p['position'])): ?>
  <button onclick="addStat(<?php echo $pid; ?>,'attack','skończony')">Skończony</button>
  <button onclick="addStat(<?php echo $pid; ?>,'attack','podbity')">Podbity</button>
  <button onclick="addStat(<?php echo $pid; ?>,'attack','blad')">Błąd</button>
<?php else: echo "-"; endif; ?>
</td>

<td>
<?php if (canSet($p['position'])): ?>
  <button onclick="addStat(<?php echo $pid; ?>,'setting','perfekcyjne')">Perfekcyjne</button>
  <button onclick="addStat(<?php echo $pid; ?>,'setting','grywalne')">Grywalne</button>
  <button onclick="addStat(<?php echo $pid; ?>,'setting','utrudniające')">Utrudniające</button>
  <button onclick="addStat(<?php echo $pid; ?>,'setting','blad')">Błąd</button>
<?php else: echo "-"; endif; ?>
</td>

<td>
  <button onclick="addStat(<?php echo $pid; ?>,'defense',1)">+1 Obrona</button>
</td>

<td>
  <button onclick="addStat(<?php echo $pid; ?>,'error',1)">+1 Błąd własny</button>
</td>

<td>
<?php if (canServe($p['position'])): ?>
  <button onclick="addStat(<?php echo $pid; ?>,'serve','as')">As</button>
  <button onclick="addStat(<?php echo $pid; ?>,'serve','trudna')">Trudna</button>
  <button onclick="addStat(<?php echo $pid; ?>,'serve','pozytywna')">Pozytywna</button>
  <button onclick="addStat(<?php echo $pid; ?>,'serve','blad')">Błąd</button>
<?php else: echo "-"; endif; ?>
</td>

<td>
<?php if (canBlock($p['position'])): ?>
  <button onclick="addStat(<?php echo $pid; ?>,'block',1)">+1 Blok</button>
<?php else: echo "-"; endif; ?>
</td>

<td>
<?php if (canBlock($p['position'])): ?>
  <button onclick="addStat(<?php echo $pid; ?>,'unblock',1)">+1 Wyblok</button>
<?php else: echo "-"; endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>

<h2>Błąd przeciwnika</h2>
<button onclick="
  fetch('update_opponent_error.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({match_id: <?php echo $match_id; ?>})
  })
  .then(res => res.json())
  .then(data => {
    if(data.success) alert('Dodano błąd przeciwnika!');
    else alert('Błąd podczas dodawania błędu przeciwnika: ' + data.error);
  })
  .catch(() => alert('Błąd sieci'));
">Dodaj błąd przeciwnika</button>

<button class="preview-stats-btn" onclick="previewStats()">
  👁️ Podgląd statystyk
</button>

<button class="finish-match-btn" onclick="finishMatch()">Zakończ mecz</button>

</body>
</html>
