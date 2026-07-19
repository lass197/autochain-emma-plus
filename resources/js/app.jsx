import '../css/app.css';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import AppLayout from './components/AppLayout';
import ProtectedRoute from './components/ProtectedRoute';
import Landing from './pages/Landing';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import Vehicles from './pages/Vehicles';
import VehicleDetail from './pages/VehicleDetail';
import Assignments from './pages/Assignments';
import Mileage from './pages/Mileage';
import Maintenances from './pages/Maintenances';
import Documents from './pages/Documents';
import Fuel from './pages/Fuel';
import Alerts from './pages/Alerts';
import Blockchain from './pages/Blockchain';

createRoot(document.getElementById('root')).render(
    <StrictMode>
        <AuthProvider>
            <BrowserRouter>
                <Routes>
                    <Route path="/" element={<Landing />} />
                    <Route path="/login" element={<Login />} />
                    <Route element={<ProtectedRoute />}>
                        <Route path="/app" element={<AppLayout />}>
                            <Route index element={<Dashboard />} />
                            <Route path="vehicles" element={<Vehicles />} />
                            <Route path="vehicles/:id" element={<VehicleDetail />} />
                            <Route path="assignments" element={<Assignments />} />
                            <Route path="mileage" element={<Mileage />} />
                            <Route path="maintenances" element={<Maintenances />} />
                            <Route path="documents" element={<Documents />} />
                            <Route path="fuel" element={<Fuel />} />
                            <Route path="alerts" element={<Alerts />} />
                            <Route path="blockchain" element={<Blockchain />} />
                        </Route>
                    </Route>
                    <Route path="*" element={<Navigate to="/" replace />} />
                </Routes>
            </BrowserRouter>
        </AuthProvider>
    </StrictMode>,
);
