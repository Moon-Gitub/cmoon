import Alpine from 'alpinejs';
import { buscadorPredictivo } from './buscador-predictivo';

window.Alpine = Alpine;
window.buscadorPredictivo = buscadorPredictivo;
Alpine.data('buscadorPredictivo', buscadorPredictivo);
Alpine.start();
