import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { ConfigProvider, theme } from 'antd';

import { Dashboard, Leaderboard, Tournaments, TournamentStandings, ImportTournament } from './pages';
import { Navbar } from './components';

function App() {
  return (
    <ConfigProvider
      theme={{
        algorithm: theme.darkAlgorithm
      }}
    >
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
    </ConfigProvider>
  );
}

export default App;
