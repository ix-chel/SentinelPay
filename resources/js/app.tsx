import React from 'react';
import { createRoot } from 'react-dom/client';
import { MainApp } from './main';
import './bootstrap';

const container = document.getElementById('root');
if (container) {
    const root = createRoot(container);
    root.render(
        <React.StrictMode>
            <MainApp />
        </React.StrictMode>
    );
}
