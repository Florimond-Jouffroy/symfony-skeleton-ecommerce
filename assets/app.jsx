/*
 * Welcome to your app's main JavaScript file!
 * This file is managed by Webpack Encore.
 */

import './styles/app.css';
import './stimulus_bootstrap';
import { registerReactControllerComponents } from '@symfony/ux-react';

registerReactControllerComponents(require.context('./react/controllers', true, /\.(j|t)sx?$/));

console.log('App.js chargé avec succès via Webpack Encore !');
