<?php
require '../../vendor/autoload.php';
require_once '../../Controllers/auth_and_db.php'; // Incluye tu conexión a MongoDB


// Verifica si el usuario está autenticado
$user = checkAuth();
if (!$user) {
    header("Location: ../../Auth/login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard de <?= htmlspecialchars($user['username']) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5">
  <h2 class="mb-4">Bienvenido, <?= htmlspecialchars($user['username']) ?></h2>
  <a href="../../Auth/logout.php" class="btn btn-danger mb-4">Cerrar sesión</a>

  <ul class="nav nav-tabs" id="tabs">
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="tab" href="#portfolio">Portfolio</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#goals">Metas</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#config">Config</a>
    </li>
  </ul>
<div class="tab-content mt-4">
    <div class="tab-pane fade show active" id="portfolio">
    <div class="mb-3 d-flex flex-wrap align-items-center gap-2">
      <button id="addMonthBtn" class="btn btn-primary">
        ➕ Añadir Mes
      </button>
      <input type="text" id="newCategoryName" class="form-control w-auto" placeholder="Nueva categoría" />
      <button id="addCategoryBtn" class="btn btn-secondary">
        ➕ Añadir Categoría
      </button>
      <button id="saveBtn" class="btn btn-success">
        💾 Guardar en servidor
      </button>
    </div>
    <table id="dataTable" class="table table-bordered">
      <thead><tr id="headerRow"></tr></thead>
      <tbody></tbody>
    </table>
    <textarea id="jsonOutput" readonly class="form-control mt-3" rows="8" placeholder="El JSON se actualiza aquí automáticamente..."></textarea>

  </div>
  <script>
    // VARIABLES GLOBALES
    let portfolioData = [];
    let categories = [];

    // REFERENCIAS A ELEMENTOS DEL DOM
    const tbody = document.querySelector('#dataTable tbody');
    const headerRow = document.getElementById('headerRow');
    const addMonthBtn = document.getElementById('addMonthBtn');
    const addCategoryBtn = document.getElementById('addCategoryBtn');
    const saveBtn = document.getElementById('saveBtn');
    const output = document.getElementById('jsonOutput');
    const newCategoryInput = document.getElementById('newCategoryName');

    // ACTUALIZA el textarea con el JSON actual
    function updateOutput() {
      output.value = JSON.stringify(portfolioData, null, 2);
    }

    // GENERA la cabecera y las filas de la tabla
    function renderTable() {
      // 1) Cabecera
      headerRow.innerHTML = '';
      const thMonth = document.createElement('th');
      thMonth.textContent = 'Mes';
      headerRow.appendChild(thMonth);

      // Añade un <th> por cada categoría
      categories.forEach((cat) => {
        const th = document.createElement('th');
        th.textContent = cat;

        // Botón para eliminar la categoría
        const delBtn = document.createElement('button');
        delBtn.textContent = '❌';
        delBtn.className = 'btn btn-sm btn-danger ms-2';
        delBtn.title = 'Eliminar categoría';
        delBtn.addEventListener('click', () => {
          if (confirm(`¿Eliminar la categoría "${cat}"?`)) {
            categories = categories.filter((c) => c !== cat);
            portfolioData.forEach((row) => delete row[cat]);
            renderTable();
          }
        });

        th.appendChild(delBtn);
        headerRow.appendChild(th);
      });

      const thAction = document.createElement('th');
      thAction.textContent = 'Acción';
      headerRow.appendChild(thAction);

      // 2) Filas
      tbody.innerHTML = '';

      // Si no hay datos, agregamos una fila indicando “Sin registros”
      if (portfolioData.length === 0) {
        const trEmpty = document.createElement('tr');
        const tdEmpty = document.createElement('td');
        tdEmpty.setAttribute('colspan', categories.length + 2);
        tdEmpty.className = 'text-center text-muted';
        tdEmpty.textContent = 'Sin datos para mostrar';
        trEmpty.appendChild(tdEmpty);
        tbody.appendChild(trEmpty);
        updateOutput();
        return;
      }

      // Si hay datos, los mostramos
      portfolioData.forEach((row, i) => {
        const tr = document.createElement('tr');

        // Celda “Mes”
        const tdMonth = document.createElement('td');
        tdMonth.contentEditable = 'true';
        tdMonth.textContent = row.month || '';
        tdMonth.addEventListener('input', (e) => {
          portfolioData[i].month = e.target.textContent.trim();
          updateOutput();
        });
        tr.appendChild(tdMonth);

        // Celdas de cada categoría
        categories.forEach((cat) => {
          const td = document.createElement('td');
          td.contentEditable = 'true';
          td.textContent = row[cat] ?? 0;
          td.addEventListener('input', (e) => {
            const val = parseFloat(e.target.textContent);
            portfolioData[i][cat] = isNaN(val) ? 0 : val;
            updateOutput();
          });
          tr.appendChild(td);
        });

        // Celda con botón de eliminar fila
        const tdAcc = document.createElement('td');
        const delBtnRow = document.createElement('button');
        delBtnRow.textContent = '🗑️';
        delBtnRow.className = 'btn btn-sm btn-outline-danger';
        delBtnRow.title = 'Eliminar fila';
        delBtnRow.addEventListener('click', () => {
          if (confirm('¿Eliminar este mes?')) {
            portfolioData.splice(i, 1);
            renderTable();
          }
        });
        tdAcc.appendChild(delBtnRow);
        tr.appendChild(tdAcc);

        tbody.appendChild(tr);
      });

      updateOutput();
    }

    // Carga inicial: consulta al backend
    fetch('../../Controllers/get_portfolio.php')
      .then((r) => {
        if (!r.ok) {
          throw new Error(`HTTP ${r.status} - ${r.statusText}`);
        }
        return r.json();
      })
      .then((data) => {
        // Asignamos el array que venga del servidor (o [])
        portfolioData = Array.isArray(data) ? data : [];

        // Construimos el array de categorías (excluyendo ‘month’)
        categories = Array.from(
          new Set(portfolioData.flatMap((d) => Object.keys(d)))
        ).filter((k) => k !== 'month');

        console.log('Datos recibidos:', portfolioData);
        console.log('Categorías detectadas:', categories);

        renderTable();
      })
      .catch((err) => {
        console.error('Error al cargar portfolioData:', err);
        // En caso de error, forzamos un array vacío y renderizamos
        portfolioData = [];
        categories = [];
        renderTable();
      });

    // Botón “Añadir mes”
    addMonthBtn.addEventListener('click', () => {
      const nuevo = { month: '' };
      categories.forEach((cat) => (nuevo[cat] = 0));
      portfolioData.push(nuevo);
      renderTable();
    });

    // Botón “Añadir categoría”
    addCategoryBtn.addEventListener('click', () => {
      const cat = newCategoryInput.value.trim();
      if (!cat) {
        alert('Por favor, escribe un nombre de categoría.');
        return;
      }
      if (cat.toLowerCase() === 'month' || categories.includes(cat)) {
        alert('Categoría inválida o ya existe.');
        return;
      }
      categories.push(cat);
      portfolioData.forEach((row) => (row[cat] = 0));
      newCategoryInput.value = '';
      renderTable();
    });

    // Botón “Guardar en el servidor”
    saveBtn.addEventListener('click', () => {
      fetch('../../Controllers/update_portfolio.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(portfolioData),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status === 'ok') {
            alert('✅ Datos guardados correctamente.');
          } else {
            alert('❌ Error al guardar: ' + (data.error || 'Desconocido'));
          }
        })
        .catch((err) => {
          alert('❌ Error al conectar con el servidor.');
          console.error(err);
        });
    });
  </script>

    <div class="tab-pane fade" id="goals">
      <div class="row">
        <?php foreach ($user['userData']['goals'] as $goal): ?>
          <div class="col-md-6 mb-3">
            <div class="card">
              <div class="card-body">
                <h5 class="card-title"><?= htmlspecialchars($goal['nombre']) ?></h5>
                <p class="card-text"><?= htmlspecialchars($goal['descripcion']) ?></p>
                <p><strong>Progreso:</strong> <?= $goal['progreso'] ?>/<?= $goal['objetivo'] ?></p>
                <p><small>Inicio: <?= $goal['fechaInicio'] ?> | Fin: <?= $goal['fecha'] ?></small></p>
                <p>
                  <?php foreach ($goal['tags'] as $tag): ?>
                    <span class="badge bg-secondary"><?= htmlspecialchars($tag) ?></span>
                  <?php endforeach; ?>
                </p>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="tab-pane fade" id="config">
        <h4>Colores</h4>
        <div id="colorEditorContainer"></div>           
    </div>

  </div>
</div>

  <script>
const colorMap = <?= json_encode($user['config']['colors']) ?>;

function getTextColor(bgColor) {
  // Simple luminance check para texto claro/oscuro
  const c = bgColor.substring(1); // quitar #
  const rgb = parseInt(c, 16);
  const r = (rgb >> 16) & 0xff;
  const g = (rgb >> 8) & 0xff;
  const b = rgb & 0xff;
  const luminance = 0.299*r + 0.587*g + 0.114*b;
  return luminance > 186 ? '#000' : '#fff';
}

function generateColorMapEditor() {
  let rows = '';
  Object.entries(colorMap).forEach(([key, color]) => {
    const textColor = getTextColor(color);
    rows += `
      <div class="editor-row d-flex align-items-center justify-content-between p-2 rounded" style="background:#fff; margin-bottom:8px; box-shadow: 0 0 5px rgba(0,0,0,0.1);">
        <label class="fw-semibold flex-grow-1 m-0" style="color:#333;">${key}</label>
        <div class="d-flex align-items-center gap-2">
          <input type="color" name="${key}" value="${color}" title="${color}" style="width:40px; height:24px; border:none; cursor:pointer;" />
          <span style="
            background: ${color};
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            color: ${textColor};
            font-size: 0.85rem;
            font-family: monospace;
          ">${color}</span>
        </div>
      </div>
    `;
  });

  return `
    <div class="editor-card bg-white p-3 rounded" style="min-width:300px;">
      <h2 class="text-center text-secondary mb-3" style="font-size:1.2rem;">Editar Colores</h2>
      <form id="colorMapForm">
        ${rows}
        <div class="text-center mt-3">
          <button type="submit" class="btn btn-success w-100 fw-bold">Guardar</button>
        </div>
      </form>
      <div id="saveStatus" class="text-center mt-2"></div>
    </div>
  `;
}
function attachColorInputListeners() {
  const inputs = document.querySelectorAll('#colorMapForm input[type="color"]');
  inputs.forEach(input => {
    input.addEventListener('input', (e) => {
      const color = e.target.value;
      const span = e.target.nextElementSibling; // El <span> justo después del input
      if (span) {
        span.style.backgroundColor = color;
        // Cambiar color de texto según luminancia para buena legibilidad
        const textColor = getTextColor(color);
        span.style.color = textColor;
        span.textContent = color;
      }
    });
  });
}

document.getElementById('colorEditorContainer').innerHTML = generateColorMapEditor();
attachColorInputListeners();


document.getElementById('colorMapForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const formData = new FormData(e.target);
  const dataToSend = {};
  formData.forEach((value, key) => {
    dataToSend[key] = value;
  });

  // Mostrar feedback
  const statusDiv = document.getElementById('saveStatus');
  statusDiv.textContent = 'Guardando...';

  try {
    const resp = await fetch('../../Controllers/update_colors.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(dataToSend)
    });
    const result = await resp.json();
    if(result.success) {
      statusDiv.textContent = 'Colores guardados correctamente.';
    } else {
      statusDiv.textContent = 'Error al guardar los colores.';
    }
  } catch (err) {
    statusDiv.textContent = 'Error de conexión.';
  }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
