import './bootstrap';
import 'bootstrap';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

window.Chart = Chart;