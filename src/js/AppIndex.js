
      // ---------------------------------------
      // 1) Funciones auxiliares para formato
      // ---------------------------------------
      function formatearMoneda(valor) {
        return valor.toLocaleString("es-ES", {
          style: "currency",
          currency: "USD",
          minimumFractionDigits: 2,
        });
      }

      function formatearPorcentaje(valor) {
        // Recibe valor como proporción (por ejemplo, 0.2682) y convierte a "26.82%"
        const signo = valor > 0 ? "+" : valor < 0 ? "" : "";
        return signo + (valor * 100).toFixed(2) + "%";
      }

      // Convierte "Mayo 2025" → { month: 5, year: 2025 }
      function desglosarMes(mesStr) {
        const mapping = {
          "Enero": 1,
          "Febrero": 2,
          "Marzo": 3,
          "Abril": 4,
          "Mayo": 5,
          "Junio": 6,
          "Julio": 7,
          "Agosto": 8,
          "Septiembre": 9,
          "Octubre": 10,
          "Noviembre": 11,
          "Diciembre": 12
        };
        const [mesNombre, añoStr] = mesStr.split(" ");
        return {
          month: mapping[mesNombre] || 0,
          year: parseInt(añoStr, 10) || 0
        };
      }

      // ---------------------------------------
      // 2) Procesar datos recibidos y dibujar
      // ---------------------------------------
      function cargarYProcesar() {
        const data = serverPortfolioData;

        // Obtener fecha actual desde el navegador
        const fechaActual = new Date();
        const mesActualNum = fechaActual.getMonth() + 1; // JavaScript: 0 = enero
        const añoActual = fechaActual.getFullYear();

        // Buscar el objeto que coincida con mes/año actual
        let mesActualData = data.find(item => {
          const desglosado = desglosarMes(item.month);
          return (
            desglosado.month === mesActualNum &&
            desglosado.year === añoActual
          );
        });

        // Si no existe registro para mes actual, tomar último disponible
        if (!mesActualData) {
          mesActualData = data[data.length - 1];
        }

        // Índice del mes actual en el array
        const idxActual = data.indexOf(mesActualData);
        // Mes anterior (si existe)
        const mesAnteriorData = idxActual > 0 ? data[idxActual - 1] : null;

        // Extraer todos los nombres de “carteras” (todas las keys menos "month")
        const todasKeys = Object.keys(mesActualData).filter(k => k !== "month");

        // ---------------------------------------
        // Calcular total de balances para este mes
        // ---------------------------------------
        const valores = todasKeys.map(k => mesActualData[k]);
        const total = valores.reduce((a, b) => a + b, 0);

        // --------------------------------------------------
        // 3) Generar las barras dinámicas (sin Chart.js) usando lógica proporcionada
        // --------------------------------------------------
        const barContainer = document.getElementById("bar-chart");
        barContainer.innerHTML = ""; // Limpiar contenido previo

        todasKeys.forEach((cartera, i) => {
          const valor = mesActualData[cartera] || 0;
          const porcentaje = total > 0 ? (valor / total) * 100 : 0;

          // <div class="..."> con altura proporcional
          const divBar = document.createElement("div");
          divBar.classList.add("border-[#6a7581]", "bg-[#f1f2f4]", "border-t-2", "w-full");
          // Asignar altura en porcentaje (redondeamos a entero para simplificar)
          divBar.style.height = porcentaje.toFixed(0) + "%";
          barContainer.appendChild(divBar);

          // <p class="...">Nombre
          const pNombre = document.createElement("p");
          pNombre.classList.add(
            "text-[#6a7581]",
            "text-[13px]",
            "font-bold",
            "leading-normal",
            "tracking-[0.015em]"
          );
          pNombre.textContent = cartera;
          barContainer.appendChild(pNombre);
        });

        // --------------------------------------------------
        // 4) Rellenar la TABLA de Carteras
        // --------------------------------------------------
        const tbody = document.getElementById("portfolio-table-body");
        tbody.innerHTML = "";

        todasKeys.forEach(cartera => {
          const valorActual = mesActualData[cartera] || 0;
          let cambio = null;
          let volatilidad = null;

          if (mesAnteriorData && cartera in mesAnteriorData) {
            const valorAnterior = mesAnteriorData[cartera] || 0;
            cambio = valorActual - valorAnterior;
            if (valorAnterior !== 0) {
              volatilidad = (Math.abs(cambio) / valorAnterior) * 100;
            } else {
              volatilidad = null;
            }
          }

          const tr = document.createElement("tr");
          tr.classList.add("border-t", "border-[#dde0e3]");

          // Nombre
          const tdNombre = document.createElement("td");
          tdNombre.classList.add("px-4", "py-2", "text-[#121416]", "text-sm");
          tdNombre.textContent = cartera;
          tr.appendChild(tdNombre);

          // Balance
          const tdBalance = document.createElement("td");
          tdBalance.classList.add("px-4", "py-2", "text-[#6a7581]", "text-sm");
          tdBalance.textContent = formatearMoneda(valorActual);
          tr.appendChild(tdBalance);

          // Cambio vs mes anterior (color dinámico)
          const tdCambio = document.createElement("td");
          tdCambio.classList.add("px-4", "py-2", "text-sm");
          if (cambio === null) {
            tdCambio.textContent = "–";
            tdCambio.classList.add("text-gray-500");
          } else {
            const signo = cambio > 0 ? "+" : "";
            tdCambio.textContent = signo + formatearMoneda(cambio);
            tdCambio.classList.add(
              cambio > 0 ? "text-green-600" : cambio < 0 ? "text-red-600" : "text-gray-500"
            );
          }
          tr.appendChild(tdCambio);

          // Volatilidad (color dinámico)
          const tdVol = document.createElement("td");
          tdVol.classList.add("px-4", "py-2", "text-sm");
          if (volatilidad === null) {
            tdVol.textContent = "–";
            tdVol.classList.add("text-gray-500");
          } else {
            tdVol.textContent = formatearPorcentaje(volatilidad / 100);
            tdVol.classList.add(
              volatilidad > 0 ? "text-green-600" : volatilidad < 0 ? "text-red-600" : "text-gray-500"
            );
          }
          tr.appendChild(tdVol);

          tbody.appendChild(tr);
        });

        // --------------------------------------------------
        // 5) Rellenar el BLOQUE de Resumen
        // --------------------------------------------------
        document.getElementById("balance-total").textContent = formatearMoneda(total);

        // Rentabilidad anualizada
        if (mesAnteriorData) {
          const balanceAnterior = Object.keys(mesAnteriorData)
            .filter(k => k !== "month")
            .reduce((sum, k) => sum + (mesAnteriorData[k] || 0), 0);

          if (balanceAnterior > 0) {
            const rentabilidadMensual = (total - balanceAnterior) / balanceAnterior;
            const rentabilidadAnualizada = Math.pow(1 + rentabilidadMensual, 12) - 1;
            document.getElementById("annualized-return").textContent = formatearPorcentaje(rentabilidadAnualizada);
          } else {
            document.getElementById("annualized-return").textContent = "–";
          }
        } else {
          document.getElementById("annualized-return").textContent = "–";
        }

        // Cambio total vs mes anterior (para el bloque "Cambio de hoy")
        let cambioTotal = 0;
        let huboCambioPrevio = false;
        if (mesAnteriorData) {
          todasKeys.forEach(k => {
            const actual = mesActualData[k] || 0;
            const anterior = mesAnteriorData[k] || 0;
            cambioTotal += actual - anterior;
            huboCambioPrevio = true;
          });
        }

        if (!huboCambioPrevio) {
          document.getElementById("pct-change-total").textContent = "–";
        } else {
          const pctCambioTotal = cambioTotal / (total - cambioTotal);
          document.getElementById("pct-change-total").textContent = formatearPorcentaje(pctCambioTotal);
        }

        document.getElementById("change-today").textContent =
          (cambioTotal >= 0 ? "+" : "") + formatearMoneda(cambioTotal);

        // Volatilidad total (promedio de las carteras)
        let sumaVols = 0;
        let contVols = 0;
        if (mesAnteriorData) {
          todasKeys.forEach(k => {
            const a = mesActualData[k] || 0;
            const b = mesAnteriorData[k] || 0;
            if (b !== 0) {
              sumaVols += (Math.abs(a - b) / b) * 100;
              contVols++;
            }
          });
        }
        document.getElementById("volatility-total").textContent = contVols > 0 ?
          formatearPorcentaje((sumaVols / contVols) / 100) :
          "–";
      }

      // Ejecutar cuando cargue la página
      window.addEventListener("DOMContentLoaded", cargarYProcesar);