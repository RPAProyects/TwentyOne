
    
document.addEventListener("DOMContentLoaded", () => {
    const checkboxes = document.querySelectorAll(".chart-checkbox");

    // Aplicar estado guardado a checkboxes y divs
    Object.entries(userGraphConfig).forEach(([id, visible]) => {
        const el = document.getElementById(id);
        if (el) el.style.display = visible ? 'flex' : 'none';
        const chk = document.querySelector(`.chart-checkbox[data-target="${id}"]`);
        if (chk) chk.checked = visible;
    });

    checkboxes.forEach((checkbox) => {
        checkbox.addEventListener("change", () => {
            const targetId = checkbox.dataset.target;
            const targetEl = document.getElementById(targetId);
            if (targetEl) {
                targetEl.style.display = checkbox.checked ? "flex" : "none";
            }

            // Guardar configuración en backend
            enviarConfigGrafica();

            // Redimensionar todos los gráficos visibles
            Object.entries(window.myCharts || {}).forEach(([id, chart]) => {
                const el = document.getElementById(id);
                if (el && el.style.display !== "none") {
                    chart.resize();
                }
            });
        });

        // Inicializar visibilidad en carga y forzar resize
        checkbox.dispatchEvent(new Event('change'));
    });
});

// Función para enviar configuración al servidor
function enviarConfigGrafica() {
    const graphConfig = {};
    document.querySelectorAll('.chart-checkbox').forEach(chk => {
        graphConfig[chk.dataset.target] = chk.checked;
    });

    fetch('../../Controllers/update_graph_config.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            graph: graphConfig
        }),
        credentials: 'include'
    }).then(res => res.json())
        .then(data => {
            if (!data.success) {
                console.error('Error guardando configuración:', data.error || data.message);
            }
        })
        .catch(err => console.error('Fetch error:', err));
}



// === FUNCIONES AUXILIARES (mismas que antes) ===
function formatearEnTooltip(value) {
    return Number(value).toLocaleString("es-ES", {
        style: "currency",
        currency: "EUR",
        minimumFractionDigits: 2
    });
}

function calcVol(logReturns) {
    const avg = logReturns.reduce((a, b) => a + b, 0) / logReturns.length;
    const variance = logReturns.reduce((a, b) => a + Math.pow(b - avg, 2), 0) / logReturns.length;
    return Math.sqrt(variance) * 100;
}

function linReg(x, y) {
    const n = x.length;
    const sx = x.reduce((a, b) => a + b, 0);
    const sy = y.reduce((a, b) => a + b, 0);
    const sxy = x.reduce((s, xi, i) => s + xi * y[i], 0);
    const sx2 = x.reduce((s, xi) => s + xi * xi, 0);
    const m = (n * sxy - sx * sy) / (n * sx2 - sx * sx);
    const b = (sy - m * sx) / n;
    const yPred = x.map(xi => m * xi + b);
    const my = sy / n;
    const ssT = y.reduce((s, yi) => s + (yi - my) ** 2, 0);
    const ssR = y.reduce((s, yi, i) => s + (yi - yPred[i]) ** 2, 0);
    return {
        yPred,
        m,
        b,
        r2: 1 - ssR / ssT
    };
}

document.addEventListener("DOMContentLoaded", () => {
    // ==================================================
    // 1) Construir un array combinado para ordenar:
    //    [{ label: "Agosto 2024", value: 635 }, …]
    // ==================================================
    const combined = rawLabels.map((label, idx) => ({
        label: label, // "Agosto 2024"
        value: rawData[idx] // 635
    }));

    // ==================================================
    // 2) Mapeo de mes en español a número (1–12)
    // ==================================================
    const mesesEnEspañol = {
        'Enero': 1,
        'Febrero': 2,
        'Marzo': 3,
        'Abril': 4,
        'Mayo': 5,
        'Junio': 6,
        'Julio': 7,
        'Agosto': 8,
        'Septiembre': 9,
        'Octubre': 10,
        'Noviembre': 11,
        'Diciembre': 12
    };

    // ==================================================
    // 3) Ordenar `combined` por año y mes (extraídos de `label`)
    // ==================================================
    combined.sort((a, b) => {
        // a.label = "Agosto 2024", b.label = "Septiembre 2024"
        const [mesA, anioA_str] = a.label.split(' ');
        const [mesB, anioB_str] = b.label.split(' ');
        const anioA = parseInt(anioA_str, 10);
        const anioB = parseInt(anioB_str, 10);
        if (anioA !== anioB) {
            return anioA - anioB;
        }
        const numMesA = mesesEnEspañol[mesA] || 0;
        const numMesB = mesesEnEspañol[mesB] || 0;
        return numMesA - numMesB;
    });

    // ==================================================
    // 4) Extraer dos arrays ya ordenados:
    //    - chartLabelsSorted: ["Agosto 2024", "Septiembre 2024", …]
    //    - chartDataSorted  : [635, 719, 765, …]
    // ==================================================

    const chartLabelsSorted = combined.map(item => item.label);
    const chartDataSorted = combined.map(item => item.value);
    // 

    function showToast(message, duration = 4500, type = 'warning') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = message;

        container.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        setTimeout(() => {
            toast.classList.remove('show');
            toast.classList.add('hide');
            toast.addEventListener('transitionend', (e) => {
                if (e.propertyName === 'height') {
                    toast.remove();
                }
            });
        }, duration);
    }

    // --- Cálculos métricas financieras ---
    function calcHHI(assetSeries, lastIndex) {
        const total = Object.values(assetSeries).reduce((sum, serie) => sum + serie[lastIndex], 0);
        if (total === 0) return 0;
        let hhi = 0;
        for (const activo in assetSeries) {
            const peso = assetSeries[activo][lastIndex] / total;
            hhi += peso * peso * 10000;
        }
        return hhi;
    }

    function calcReturns(rawData) {
        const logReturns = [];
        for (let i = 1; i < rawData.length; i++) {
            if (rawData[i - 1] <= 0) continue;
            logReturns.push(Math.log(rawData[i] / rawData[i - 1]));
        }
        return logReturns;
    }

    function calcAnnualizedReturn(rawData, months) {
        if (rawData.length < 2) return 0;
        const totalReturn = rawData[rawData.length - 1] / rawData[0] - 1;
        const years = months / 12;
        return (Math.pow(1 + totalReturn, 1 / years) - 1) * 100;
    }

    function calcMaxDrawdown(rawData) {
        let peak = rawData[0];
        let maxDd = 0;
        let drawdownStart = 0;
        let drawdownPeriods = [];
        let currentDdStart = 0;
        let inDd = false;

        for (let i = 1; i < rawData.length; i++) {
            if (rawData[i] > peak) {
                peak = rawData[i];
                if (inDd) {
                    drawdownPeriods.push(i - currentDdStart);
                    inDd = false;
                }
            } else {
                const dd = (rawData[i] - peak) / peak;
                if (dd < maxDd) maxDd = dd;
                if (!inDd) {
                    currentDdStart = i - 1;
                    inDd = true;
                }
            }
        }
        if (inDd) drawdownPeriods.push(rawData.length - currentDdStart);

        return {
            maxDrawdown: maxDd * 100,
            maxDrawdownDur: Math.max(...drawdownPeriods, 0)
        };
    }

    function calcStability(rawData) {
        let positive = 0;
        let total = 0;
        for (let i = 1; i < rawData.length; i++) {
            if (rawData[i] > rawData[i - 1]) positive++;
            total++;
        }
        return total === 0 ? 0 : (positive / total) * 100;
    }

    function calcSharpeRatio(logReturns, riskFreeRate = 0) {
        if (logReturns.length === 0) return 0;
        const avg = logReturns.reduce((a, b) => a + b, 0) / logReturns.length;
        const std = Math.sqrt(logReturns.reduce((a, b) => a + Math.pow(b - avg, 2), 0) / logReturns.length);
        if (std === 0) return 0;
        const excessReturn = avg - riskFreeRate / 12;
        return (excessReturn / std) * Math.sqrt(12);
    }

    function calcSortinoRatio(logReturns, riskFreeRate = 0) {
        if (logReturns.length === 0) return 0;
        const avg = logReturns.reduce((a, b) => a + b, 0) / logReturns.length;
        const negativeReturns = logReturns.filter(r => r < 0);
        const stdDown = Math.sqrt(negativeReturns.reduce((a, b) => a + b * b, 0) / (negativeReturns.length || 1));
        if (stdDown === 0) return 0;
        const excessReturn = avg - riskFreeRate / 12;
        return (excessReturn / stdDown) * Math.sqrt(12);
    }



    const nMonths = rawData.length;
    const lastIdx = nMonths - 1;

    const hhi = calcHHI(assetSeries, lastIdx);
    const rentAnual = calcAnnualizedReturn(rawData, nMonths);
    const {
        maxDrawdown: md,
        maxDrawdownDur
    } = calcMaxDrawdown(rawData);
    const stability = calcStability(rawData);

    const logReturns = calcReturns(rawData);
    const sr = calcSharpeRatio(logReturns);
    const sortino = calcSortinoRatio(logReturns);

    const alertas = [];
    const tipos = [];


    // Obtener fecha actual
    const ahora = new Date();
    let mesActualNum = ahora.getMonth() + 1; // getMonth() va de 0 (enero) a 11 (diciembre)
    let anioActual = ahora.getFullYear();

    // Calcular mes anterior al actual
    let prevMesNum = mesActualNum - 1;
    let prevAnio = anioActual;
    if (prevMesNum === 0) {
        prevMesNum = 12;
        prevAnio -= 1;
    }

    // Encontrar nombre del mes anterior
    const mesNombreAnterior = Object.entries(mesesEnEspañol)
        .find(([, num]) => num === prevMesNum)?.[0];

    // Crear string "Mes Año" del mes anterior
    const prevMonthYear = `${mesNombreAnterior} ${prevAnio}`;

    // Normalizar y comparar con labels existentes...
    function normalize(str) {
        return str
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/\s+/g, " ")
            .trim()
            .toLowerCase();
    }

    const normalizedLabels = chartLabelsSorted.map(label => normalize(label));
    const normalizedPrevMonthYear = normalize(prevMonthYear);

    if (!normalizedLabels.includes(normalizedPrevMonthYear)) {
        alertas.push(`${prevMonthYear} no está registrado.`);
        tipos.push("warning");
    }

   // Define mínimos para cada alerta
const MIN_MESES_HHI = 3;
const MIN_MESES_RENT_ANUAL = 6;
const MIN_MESES_MAX_DD = 6;
const MIN_MESES_SHARPE_SORTINO = 6;
const MIN_MESES_MAX_DD_DUR = 6;
const MIN_MESES_STABILITY = 3;

if (nMonths >= MIN_MESES_HHI && hhi > 2500) {
    alertas.push(`Portafolio poco diversificado (HHI ${hhi.toFixed(2)} > 2500)`);
    tipos.push('info');
}

if (nMonths >= MIN_MESES_RENT_ANUAL && rentAnual < 0) {
    alertas.push(`Rentabilidad anualizada negativa: ${rentAnual.toFixed(2)}%`);
    tipos.push('warning');
}

if (nMonths >= MIN_MESES_MAX_DD && md < -20) {
    alertas.push(`Máximo drawdown > 20%: ${md.toFixed(2)}%`);
    tipos.push('warning');
}

if (nMonths >= MIN_MESES_SHARPE_SORTINO && sr < 0.5) {
    alertas.push(`Sharpe ratio bajo (<0.5): ${sr.toFixed(2)}`);
    tipos.push('info');
}

if (nMonths >= MIN_MESES_SHARPE_SORTINO && sortino < 1) {
    alertas.push(`Sortino ratio bajo (<1): ${sortino.toFixed(2)}`);
    tipos.push('info');
}

if (nMonths >= MIN_MESES_MAX_DD_DUR && maxDrawdownDur > 6) {
    alertas.push(`Drawdown > 6 meses: ${maxDrawdownDur} meses`);
    tipos.push('warning');
}

if (nMonths >= MIN_MESES_STABILITY && stability < 50) {
    alertas.push(`< 50% meses positivos: ${stability.toFixed(2)}%`);
    tipos.push('info');
}

if (alertas.length === 0) {
    alertas.push('No hay alertas de riesgo con los datos disponibles.');
    tipos.push('success');
}


    if (alertas.length) {
        alertas.forEach((msg, i) => {
            setTimeout(() => showToast(msg, 5000, tipos[i]), i * 1000);
        });
        const totalDuration = alertas.length * 1000 + 5000;
  setTimeout(() => {
        const container = document.getElementById('toast-container');
        if (container) {
            container.remove();
        }
    }, totalDuration);
    } else {
        showToast('✅ Portfolio sin alertas de riesgo.', 3000, 'success');
        setTimeout(() => {
        const container = document.getElementById('toast-container');
        if (container) {
            container.remove();
        }
    }, 3000);
    }

    console.log("Values:", rawData);
    console.log("Asset Series:", assetSeries);



    // ==========================================
    // === GRÁFICO 1: Valor Total (Line Chart) ===
    // ==========================================
    (function () {
        const chartDom = document.getElementById("line-chart");
        const myChart = echarts.init(chartDom);

        // Gradiente (igual que antes)
        const gradient = new echarts.graphic.LinearGradient(0, 0, 0, 1, [{
            offset: 0,
            color: "rgba(30, 58, 138, 0)"
        },
        {
            offset: 1,
            color: "rgba(30, 58, 138, 0)"
        }
        ]);

        const option = {
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'line'
                },
                formatter: params => {
                    return formatearEnTooltip(params[0].value);
                },
                textStyle: {
                    fontSize: 12,
                    fontFamily: "Public Sans, sans-serif"
                },
                extraCssText: 'padding:8px;'
            },
            xAxis: {
                type: 'category',
                data: chartLabelsSorted,
                axisLine: {
                    show: false
                },
                axisTick: {
                    show: false
                },
                axisLabel: {
                    color: "#637488",
                    fontSize: 12,
                    fontFamily: "Public Sans, sans-serif"
                },
                splitLine: {
                    show: false
                }
            },
            yAxis: {
                type: 'value',
                axisLine: {
                    show: false
                },
                axisTick: {
                    show: false
                },
                axisLabel: {
                    show: false
                },
                splitLine: {
                    show: false
                }
            },
            grid: {
                left: '5%',
                right: '5%',
                bottom: '5%',
                top: '5%'
            },
            series: [{
                name: "Valor total",
                type: 'line',
                data: chartDataSorted,
                smooth: true,
                symbol: 'none',
                lineStyle: {
                    color: "#1E3A8A",
                    width: 2
                },
                areaStyle: {
                    color: gradient
                },
                emphasis: {
                    focus: 'series',
                    itemStyle: {
                        borderColor: "#1E3A8A",
                        borderWidth: 2,
                        color: "#1E3A8A"
                    }
                }
            }],
            legend: {
                show: false
            }
        };

        myChart.setOption(option);
        window.addEventListener('resize', () => myChart.resize());
    })();


    // ====================================================================================
    // === GRÁFICO 2: Volatilidad y Retorno (Mixed Bar + Line) con tooltip y sin hover en barras ===
    // ====================================================================================
    (function () {
        // “meses” usa la lista ordenada, pero sin el primer elemento
        const meses = chartLabelsSorted.slice(1); // ["Septiembre 2024", "Octubre 2024", …]
        const dataTotales = chartDataSorted; // [635, 719, 765, …]

        // Calcular retorno % y volatilidades a partir de dataTotales
        const retornoPct = [],
            retornoVal = [],
            vol3m = [],
            vol6m = [];

        for (let i = 1; i < dataTotales.length; i++) {
            const prev = dataTotales[i - 1];
            const curr = dataTotales[i];
            const pct = prev !== 0 ? ((curr - prev) / prev) * 100 : 0;
            retornoPct.push(parseFloat(pct.toFixed(2)));
            retornoVal.push(curr - prev);

            // Volatilidad 3M
            const logs3 = [];
            for (let j = Math.max(1, i - 2); j <= i; j++) {
                if (dataTotales[j - 1] > 0 && dataTotales[j] > 0) {
                    logs3.push(Math.log(dataTotales[j] / dataTotales[j - 1]));
                }
            }
            vol3m.push(logs3.length >= 2 ? parseFloat(calcVol(logs3).toFixed(2)) : 0);

            // Volatilidad 6M
            const logs6 = [];
            for (let j = Math.max(1, i - 5); j <= i; j++) {
                if (dataTotales[j - 1] > 0 && dataTotales[j] > 0) {
                    logs6.push(Math.log(dataTotales[j] / dataTotales[j - 1]));
                }
            }
            vol6m.push(logs6.length >= 2 ? parseFloat(calcVol(logs6).toFixed(2)) : 0);
        }

        const retornoNums = retornoPct.map(v => v);
        const legendColorMap2 = {
            "Retorno %": "rgba(0, 0, 0, 0.2)",
            "Volatilidad 3M": "#717b7a",
            "Volatilidad 6M": "#b0bec5"
        };

        const legendData2 = Object.keys(legendColorMap2).map(name => ({
            name,
            icon: 'circle',
            textStyle: {
                color: legendColorMap2[name],
                fontSize: 12
            },
            itemStyle: {
                color: legendColorMap2[name]
            }
        }));

        const chartDom2 = document.getElementById("volatility-chart");
        const myChart2 = echarts.init(chartDom2);

        const option2 = {
            tooltip: {
                trigger: 'axis',
                formatter: params => {
                    const idx = params[0].dataIndex;
                    const mes = meses[idx];
                    const pct = retornoPct[idx].toFixed(2) + "%";
                    const valEuros = retornoVal[idx].toLocaleString('es-ES', {
                        style: 'currency',
                        currency: 'EUR'
                    });
                    const v3 = vol3m[idx].toFixed(2) + "%";
                    const v6 = vol6m[idx].toFixed(2) + "%";

                    return [
                        `<b>${mes}</b>`,
                        `Retorno %: ${pct}`,
                        `Valor: ${valEuros}`,
                        `Volatilidad 3M: ${v3}`,
                        `Volatilidad 6M: ${v6}`
                    ].join('<br/>');
                },
                textStyle: {
                    fontSize: 12,
                    fontFamily: "Public Sans, sans-serif"
                },
                extraCssText: 'padding:8px;'
            },
            legend: {
                top: 0,
                data: legendData2
            },
            dataZoom: [{
                type: 'inside',
                xAxisIndex: 0,
                filterMode: 'none'
            }],
            xAxis: {
                type: 'category',
                data: meses, // ["Septiembre 2024", "Octubre 2024", …]
                axisLabel: {
                    color: "#637488"
                },
                splitLine: {
                    show: false
                },
                axisLine: {
                    lineStyle: {
                        color: '#eee'
                    }
                }
            },
            yAxis: {
                type: 'value',
                axisLabel: {
                    color: "#637488",
                    formatter: value => value + "%"
                },
                splitLine: {
                    show: false
                }
            },
            series: [{
                name: 'Retorno %',
                type: 'bar',
                data: retornoNums,
                itemStyle: {
                    color: legendColorMap2["Retorno %"]
                },
                emphasis: {
                    disabled: true
                },
                z: 1
            },
            {
                name: 'Volatilidad 3M',
                type: 'line',
                data: vol3m,
                lineStyle: {
                    color: legendColorMap2["Volatilidad 3M"],
                    type: 'dashed',
                    dashOffset: 6,
                    dashArrayWidth: 6
                },
                smooth: true,
                symbol: 'none',
                yAxisIndex: 0,
                z: 2
            },
            {
                name: 'Volatilidad 6M',
                type: 'line',
                data: vol6m,
                lineStyle: {
                    color: legendColorMap2["Volatilidad 6M"],
                    type: 'dashed',
                    dashOffset: 2,
                    dashArrayWidth: 4
                },
                smooth: true,
                symbol: 'none',
                yAxisIndex: 0,
                z: 3
            }
            ]
        };

        myChart2.setOption(option2);
        window.addEventListener('resize', () => myChart2.resize());
        window.myCharts = window.myCharts || {};
        window.myCharts['volatility-chart-block'] = myChart2;

    })();


    // ============================================================== 
    // === GRÁFICO 3: Activos individuales con Zoom/Pan (Multiseries) ===
    // ============================================================== 
    (function () {
        const chartDom3 = document.getElementById("asset-chart");
        const myChart3 = echarts.init(chartDom3);

        // Cada serie usa assetSeries[k] en el mismo orden que rawLabels
        const datasets = assetKeys.map(k => ({
            name: k,
            type: 'line',
            data: assetSeries[k],
            lineStyle: {
                color: colorMap[k] || "#ccc",
                width: 2
            },
            smooth: true,
            symbol: 'none'
        }));

        const legendData3 = assetKeys.map(k => ({
            name: k,
            icon: 'circle',
            textStyle: {
                color: colorMap[k] || "#ccc",
                fontSize: 12
            },
            itemStyle: {
                color: colorMap[k] || "#ccc"
            }
        }));

        const option3 = {
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'line'
                },
                formatter: params => {
                    return params.map(p => {
                        const val = Number(p.data).toLocaleString("es-ES", {
                            style: "currency",
                            currency: "EUR",
                            minimumFractionDigits: 2
                        });
                        return `${p.seriesName}: ${val}`;
                    }).join('<br/>');
                },
                textStyle: {
                    fontSize: 12,
                    fontFamily: "Public Sans, sans-serif"
                },
                extraCssText: 'padding:8px;'
            },
            legend: {
                top: 0,
                data: legendData3
            },
            dataZoom: [{
                type: 'inside',
                xAxisIndex: 0,
                filterMode: 'none'
            }],
            xAxis: {
                type: 'category',
                data: chartLabelsSorted, // ["Agosto 2024", "Septiembre 2024", …]
                axisLabel: {
                    color: "#637488"
                },
                axisLine: {
                    lineStyle: {
                        color: '#eee'
                    }
                }
            },
            yAxis: {
                type: 'value',
                axisLabel: {
                    color: "#637488",
                    formatter: val => val.toLocaleString("es-ES", {
                        style: "currency",
                        currency: "EUR"
                    })
                }
            },
            grid: {
                top: '15%'
            },
            series: datasets
        };

        myChart3.setOption(option3);
        window.addEventListener('resize', () => myChart3.resize());
        window.myCharts = window.myCharts || {};
        window.myCharts['asset-chart-block'] = myChart3;
    })();


    // ======================================================================
    // === GRÁFICO 4: Incremento/Decremento Mensual por Activo (Line + Zoom) ===
    // ======================================================================
    (function () {
        const chartDom4 = document.getElementById("asset-diff-chart");
        const myChart4 = echarts.init(chartDom4);

        // 1) Calcular diferencias mensuales basadas en chartDataSorted
        const assetDiffSeries = assetKeys.map(k => {
            const data = assetSeries[k];
            const diffs = [];
            for (let i = 1; i < data.length; i++) {
                diffs.push(data[i] - data[i - 1]);
            }
            return {
                name: k,
                type: 'line',
                data: diffs,
                lineStyle: {
                    color: colorMap[k] || "#ccc",
                    width: 2
                },
                smooth: true,
                symbol: 'none'
            };
        });

        // 2) Leyenda colorida
        const legendData4 = assetKeys.map(k => ({
            name: k,
            icon: 'circle',
            textStyle: {
                color: colorMap[k] || "#ccc",
                fontSize: 12
            },
            itemStyle: {
                color: colorMap[k] || "#ccc"
            }
        }));

        const option4 = {
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'line'
                },
                formatter: params => {
                    return params.map(p => {
                        const val = Number(p.data).toLocaleString("es-ES", {
                            style: "currency",
                            currency: "EUR",
                            minimumFractionDigits: 2
                        });
                        return `${p.seriesName}: ${val}`;
                    }).join('<br/>');
                },
                textStyle: {
                    fontSize: 12,
                    fontFamily: "Public Sans, sans-serif"
                },
                extraCssText: 'padding:8px;'
            },
            legend: {
                top: 0,
                data: legendData4
            },
            dataZoom: [{
                type: 'inside',
                xAxisIndex: 0,
                filterMode: 'none'
            }],
            xAxis: {
                type: 'category',
                data: chartLabelsSorted.slice(1), // omitimos el primer mes para mostrar diferencias
                axisLabel: {
                    color: "#637488"
                },
                axisLine: {
                    lineStyle: {
                        color: '#eee'
                    }
                }
            },
            yAxis: {
                type: 'value',
                axisLabel: {
                    color: "#637488",
                    formatter: val => val.toLocaleString("es-ES", {
                        style: "currency",
                        currency: "EUR"
                    })
                }
            },
            series: assetDiffSeries
        };

        myChart4.setOption(option4);
        window.addEventListener('resize', () => myChart4.resize());
        window.myCharts = window.myCharts || {};
        window.myCharts['asset-diff-chart-block'] = myChart4;
    })();


    // ========================================================================
    // === GRÁFICO 5: Regresión Lineal + Proyección (Line con R² en título) ===
    // ========================================================================
    (function () {
        // Para la regresión, “interp” son solo los valores numéricos no nulos:
        const interp = chartDataSorted.filter(v => v !== null);
        const xVals = interp.map((_, i) => i);
        const {
            yPred,
            r2
        } = linReg(xVals, interp);

        // Proyección lineal: tomamos la pendiente del último tramo
        const lastSlope = yPred.at(-1) - yPred.at(-2);
        const numPred = 6;
        const yFut = Array.from({
            length: numPred
        }, (_, i) =>
            Number((yPred.at(-1) + lastSlope * (i + 1)).toFixed(2))
        );

        const ultimoLabel = chartLabelsSorted.at(-1); // ej: "Junio 2025"
        const [mesUlt, anioUltStr] = ultimoLabel.split(" ");
        let mesNum = mesesEnEspañol[mesUlt] || 1;
        let anioNum = parseInt(anioUltStr, 10);

        // Generar los siguientes 6 meses
        const nombreMesPorNumero = [
            "", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
            "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
        ];

        const futureLabels = Array.from({
            length: numPred
        }, () => {
            mesNum++;
            if (mesNum > 12) {
                mesNum = 1;
                anioNum++;
            }
            return `${nombreMesPorNumero[mesNum]} ${anioNum}`;
        });
        const allLabels = chartLabelsSorted.concat(futureLabels);

        const realSeries = interp.concat(Array(numPred).fill(null));
        const regSeries = yPred.concat(yFut);

        const legendColorMap5 = {
            "Valor Real": "#1E3A8A",
            "Regresión + Proyección": "#8bb0a4"
        };
        const legendData5 = Object.keys(legendColorMap5).map(name => ({
            name,
            icon: 'circle',
            textStyle: {
                color: legendColorMap5[name],
                fontSize: 12
            },
            itemStyle: {
                color: legendColorMap5[name]
            }
        }));

        const chartDom5 = document.getElementById("regression-chart");
        const myChart5 = echarts.init(chartDom5);

        const option5 = {
            title: {
                text: `R²: ${r2.toFixed(4)}`,
                left: '0%',
                textStyle: {
                    color: '#444',
                    fontSize: 14,
                    fontFamily: "Public Sans, sans-serif"
                }
            },
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'line'
                },
                formatter: params => {
                    return params.map(p => {
                        const val = Number(p.data).toLocaleString("es-ES", {
                            style: "currency",
                            currency: "EUR",
                            minimumFractionDigits: 2
                        });
                        return `${p.seriesName}: ${val}`;
                    }).join('<br/>');
                },
                textStyle: {
                    fontSize: 12,
                    fontFamily: "Public Sans, sans-serif"
                },
                extraCssText: 'padding:8px;'
            },
            legend: {
                top: 0,
                data: legendData5
            },
            dataZoom: [{
                type: 'inside',
                xAxisIndex: 0,
                filterMode: 'none'
            }],
            xAxis: {
                type: 'category',
                data: allLabels, // ["Agosto 2024","Septiembre 2024",…,"Proy 1",…]
                axisLabel: {
                    color: "#637488"
                },
                splitLine: {
                    show: false
                },
                axisLine: {
                    lineStyle: {
                        color: '#eee'
                    }
                }
            },
            yAxis: {
                type: 'value',
                axisLabel: {
                    color: "#637488",
                    formatter: val => val.toLocaleString("es-ES", {
                        style: "currency",
                        currency: "EUR"
                    })
                }
            },
            grid: {
                top: '20%'
            },
            series: [{
                name: 'Valor Real',
                type: 'line',
                data: realSeries,
                lineStyle: {
                    color: legendColorMap5["Valor Real"],
                    width: 2
                },
                smooth: true,
                symbol: 'none'
            },
            {
                name: 'Regresión + Proyección',
                type: 'line',
                data: regSeries,
                lineStyle: {
                    color: legendColorMap5["Regresión + Proyección"],
                    width: 2,
                    type: 'dashed',
                    dashArray: [4, 8]
                },
                smooth: true,
                symbol: 'none'
            }
            ]
        };

        myChart5.setOption(option5);
        window.addEventListener('resize', () => myChart5.resize());
        window.myCharts = window.myCharts || {};
        window.myCharts['regression-chart-block'] = myChart5;
    })();

});