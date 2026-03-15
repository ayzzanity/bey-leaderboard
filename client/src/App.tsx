import { BrowserRouter, Routes, Route } from 'react-router-dom';

import Navbar from './components/Navbar';

import Dashboard from './pages/Dashboard';
import Leaderboard from './pages/Leaderboard';
import Tournaments from './pages/Tournaments';
import TournamentStandings from './pages/TournamentStandings';
import ImportTournament from './pages/admin/ImportTournament';

function App() {
  return (
    <BrowserRouter>
      <Navbar />

      <Routes>
        <Route path="/" element={<Dashboard />} />
        <Route path="/leaderboard" element={<Leaderboard />} />

        <Route path="/tournaments" element={<Tournaments />} />
        <Route path="/tournaments/:id" element={<TournamentStandings />} />

        <Route path="/admin/import" element={<ImportTournament />} />
      </Routes>
    </BrowserRouter>
  );
}

export default App;
