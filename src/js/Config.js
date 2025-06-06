      document.addEventListener('DOMContentLoaded', () => {
        console.log('DOM cargado: inicializando editor de colores.');

        console.log('colorMap inicial desde PHP (actualizado con faltantes):', colorMap);

        // 2) Función para saber si el texto debe ser claro u oscuro
        function getTextColor(bgColor) {
            const c = bgColor.substring(1);
            const rgb = parseInt(c, 16);
            const r = (rgb >> 16) & 0xff;
            const g = (rgb >> 8) & 0xff;
            const b = rgb & 0xff;
            const luminance = 0.299 * r + 0.587 * g + 0.114 * b;
            return luminance > 186 ? '#000000' : '#FFFFFF';
        }

        // 3) Generar HTML del editor en Tailwind + bordes grises
        function generateColorMapEditor() {
            let rows = '';

            Object.entries(colorMap).forEach(([key, color]) => {
                const textColor = getTextColor(color);
                rows += `
          <div class="flex items-center justify-between py-2 border-b border-gray-200">
            <label class="font-semibold text-gray-800 flex-grow ml-4">${key}</label>
            <div class="flex items-center gap-2 mr-4">
              <input type="color" name="${key}" value="${color}" title="${color}"
                     class="w-10 h-6 border-0 cursor-pointer rounded"/>
              <span class="rounded px-2 py-0.5 font-mono text-sm"
                    style="background: ${color}; color: ${textColor};">
                ${color}
              </span>
            </div>
          </div>`;
            });

            return `
        <div class="bg-white py-4 w-full rounded-lg border border-gray-200 mt-6">
          <form id="colorMapForm" class="space-y-4 px-4">
            ${rows}
            <div class="text-center pt-2">
              <button type="submit"
                      class="w-3/4 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium py-2 rounded transition-colors">
                Guardar
              </button>
            </div>
          </form>
          <div id="saveStatus" class="text-center mt-2 text-sm text-gray-600"></div>
        </div>`;
        }

        // 4) Asignar eventos a los inputs color
        function attachColorInputListeners() {
            const inputs = document.querySelectorAll('#colorMapForm input[type="color"]');
            console.log('Inputs de color encontrados:', inputs.length);
            inputs.forEach(input => {
                input.addEventListener('input', (e) => {
                    const color = e.target.value;
                    const span = e.target.nextElementSibling;
                    console.log(`Input [${e.target.name}] cambió a:`, color);
                    if (span) {
                        span.style.backgroundColor = color;
                        span.style.color = getTextColor(color);
                        span.textContent = color;
                    }
                });
            });
        }

        // 5) Inyectar el editor en el contenedor
        const container = document.getElementById('colorEditorContainer');
        if (!container) {
            console.error('No existe <div id="colorEditorContainer">');
            return;
        }

        container.innerHTML = generateColorMapEditor();
        console.log('Editor de colores inyectado en el DOM.');

        attachColorInputListeners();
        console.log('Listeners asignados a inputs de color.');

        // 6) Envío del formulario cuando el usuario cambia colores manualmente
        const form = document.getElementById('colorMapForm');
        if (!form) {
            console.error('No se encontró el formulario #colorMapForm');
            return;
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            console.log('Formulario de colores: submit.');

            const formData = new FormData(form);
            const dataToSend = {};
            formData.forEach((value, key) => dataToSend[key] = value);
            console.log('Datos a enviar a update_colors.php:', dataToSend);

            const statusDiv = document.getElementById('saveStatus');
            statusDiv.textContent = 'Guardando...';

            try {
                const resp = await fetch('../../Controllers/update_colors.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(dataToSend)
                });
                console.log('Respuesta raw de update_colors.php:', resp);

                const result = await resp.json();
                console.log('JSON respuesta de update_colors.php:', result);

                if (result.success) {
                    statusDiv.textContent = 'Colores guardados correctamente.';
                } else {
                    statusDiv.textContent = 'Error al guardar los colores.';
                }
            } catch (err) {
                console.error('Error en fetch a update_colors.php:', err);
                statusDiv.textContent = 'Error de conexión.';
            }
        });
    });
    
    let portfolioData = [];
        let categories = [];

        const tbody = document.querySelector('#dataTable tbody');
        const headerRow = document.getElementById('headerRow');
        const addMonthBtn = document.getElementById('addMonthBtn');
        const addCategoryBtn = document.getElementById('addCategoryBtn');
        const saveBtn = document.getElementById('saveBtn');
        const output = document.getElementById('jsonOutput');
        const newCategoryInput = document.getElementById('newCategoryName');

        const mesesValidos = [
            "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
            "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
        ];

        function updateOutput() {
            output.value = JSON.stringify(portfolioData, null, 2);
        }

        function formatearMesYAnio(input) {
            const partes = input.trim().split(' ');
            if (partes.length !== 2) return null;

            let [mes, anio] = partes;
            mes = mes.charAt(0).toUpperCase() + mes.slice(1).toLowerCase();

            if (!mesesValidos.includes(mes)) return null;

            const anioActual = new Date().getFullYear();
            const anioNum = parseInt(anio);
            if (isNaN(anioNum) || anioNum > anioActual || anio.length !== 4) return null;

            return `${mes} ${anioNum}`;
        }

        function renderTable() {
            headerRow.innerHTML = '';
            const thMonth = document.createElement('th');
            thMonth.textContent = 'Mes';
            thMonth.className = 'px-4 py-2 text-left font-medium text-[#111418]';
            headerRow.appendChild(thMonth);

            categories.forEach(cat => {
                const th = document.createElement('th');
                th.textContent = cat;
                th.className = 'px-4 py-2 text-left font-medium text-[#111418]';

                const delBtn = document.createElement('button');
                delBtn.textContent = '- Eliminar';
                delBtn.className = 'ml-2 text-sm text-red-500';
                delBtn.title = 'Eliminar categoría';
                delBtn.addEventListener('click', () => {
                    if (confirm(`¿Eliminar la categoría "${cat}"?`)) {
                        categories = categories.filter(c => c !== cat);
                        portfolioData.forEach(row => delete row[cat]);
                        renderTable();
                    }
                });

                th.appendChild(delBtn);
                headerRow.appendChild(th);
            });

            const thActions = document.createElement('th');
            thActions.textContent = 'Acciones';
            thActions.className = 'px-4 py-2 text-left font-medium text-[#111418]';
            headerRow.appendChild(thActions);

            tbody.innerHTML = '';
            if (portfolioData.length === 0) {
                const trEmpty = document.createElement('tr');
                const tdEmpty = document.createElement('td');
                tdEmpty.setAttribute('colspan', categories.length + 2);
                tdEmpty.className = 'px-4 py-6 text-center text-sm text-[#617489]';
                tdEmpty.textContent = 'Sin datos para mostrar';
                trEmpty.appendChild(tdEmpty);
                tbody.appendChild(trEmpty);
                updateOutput();
                return;
            }

            portfolioData.forEach((row, i) => {
                const tr = document.createElement('tr');
                tr.className = 'border-t border-[#dbe0e6]';

                const tdMonth = document.createElement('td');
                tdMonth.contentEditable = 'true';
                tdMonth.textContent = row.month || '';
                tdMonth.className = 'px-4 py-2 text-[#617489]';

                tdMonth.addEventListener('blur', e => {
                    const nuevoValor = e.target.textContent.trim();
                    const validado = formatearMesYAnio(nuevoValor);
                    if (!validado) {
                        alert('Mes inválido. Usa el formato: "Mes Año" (ej. Marzo 2024)');
                        e.target.textContent = row.month || '';
                    } else {
                        row.month = validado;
                        e.target.textContent = validado;
                        updateOutput();
                    }
                });

                tr.appendChild(tdMonth);

                categories.forEach(cat => {
                    const td = document.createElement('td');
                    td.contentEditable = 'true';
                    td.textContent = row[cat] ?? 0;
                    td.className = 'px-4 py-2 text-[#617489]';
                    td.addEventListener('input', e => {
                        const val = parseFloat(e.target.textContent);
                        portfolioData[i][cat] = isNaN(val) ? 0 : val;
                        updateOutput();
                    });
                    tr.appendChild(td);
                });

                const tdAcc = document.createElement('td');
                tdAcc.className = 'px-4 py-2';
                const delBtnRow = document.createElement('button');
                delBtnRow.textContent = 'Eliminar';
                delBtnRow.className = 'text-red-500';
                delBtnRow.title = 'Eliminar mes';
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

        fetch('../../Controllers/get_portfolio.php')
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(data => {
    portfolioData = Array.isArray(data) ? data : [];
    categories = Array.from(new Set(portfolioData.flatMap(d => Object.keys(d)))).filter(k => k !== 'month');

    // Si no hay categorías, crear la predeterminada "Cartera"
    if (categories.length === 0) {
        categories.push('Cartera');
        // Añadir campo "Cartera" con valor 0 a cada fila (o crear una fila si no hay datos)
        if (portfolioData.length === 0) {
            // No hay datos, agregamos un mes actual con "Cartera" = 0
            const fecha = new Date();
            const mes = mesesValidos[fecha.getMonth()];
            const anio = fecha.getFullYear();
            portfolioData.push({ month: `${mes} ${anio}`, Cartera: 0 });
        } else {
            // Hay filas, agregamos la categoría a cada una
            portfolioData.forEach(row => {
                row['Cartera'] = 0;
            });
        }
    }

    renderTable();
})
            .catch(err => {
                console.error('Error al cargar portfolioData:', err);
                portfolioData = [];
                categories = [];
                renderTable();
            });

        addMonthBtn.addEventListener('click', () => {
            const fecha = new Date();
            const mes = mesesValidos[fecha.getMonth()];
            const anio = fecha.getFullYear();
            const nuevo = {
                month: `${mes} ${anio}`
            };
            categories.forEach(cat => nuevo[cat] = 0);
            portfolioData.push(nuevo);
            renderTable();
        });

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
            portfolioData.forEach(row => row[cat] = 0);
            newCategoryInput.value = '';
            renderTable();
        });

        saveBtn.addEventListener('click', () => {
            fetch('../../Controllers/update_portfolio.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(portfolioData)
                })
                .then(resp => resp.json())
                .then(res => {
                    if (res.status === 'ok') {
                        // Llamar a sortportfolio.php después de guardar
                        return fetch('../../Controllers/sortportfolio.php')
                            .then(sortResp => sortResp.json())
                            .then(sortRes => {
                                if (sortRes.status === 'ok') {
                                    alert('Datos guardados correctamente.');
                                } else {
                                    alert('⚠️ Guardado bien, pero error al ordenar: ' + (sortRes.message || 'Desconocido'));
                                }
                            });
                    } else {
                        alert('❌ Error al guardar: ' + (res.error || 'Desconocido'));
                    }
                })
                .catch(err => {
                    alert('❌ Error al conectar con el servidor.');
                    console.error(err);
                });
        });