import './bootstrap';
import 'bootstrap';
import { Chart, registerables } from 'chart.js';
import './tiptap-editor';

Chart.register(...registerables);

window.Chart = Chart;