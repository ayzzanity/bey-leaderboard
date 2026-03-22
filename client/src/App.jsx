import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { ConfigProvider, Layout } from 'antd';

import { Dashboard, Leaderboard, PlayerProfile, Tournaments, TournamentStandings, ImportTournament } from './pages';
import { Navbar } from './components';
import { useBootstrapTheme } from './config/themes';

const { Header, Content } = Layout;

function App() {
  const configProps = useBootstrapTheme();

  return (
    <ConfigProvider {...configProps}>
      <BrowserRouter>
        <Header style={{ display: 'flex', alignItems: 'center', justifyItems: 'space-between' }}>
          <div className="demo-logo" />
          <Navbar />
        </Header>
        <Content style={{ padding: '0 48px' }}>
          <Routes>
            <Route path="/" element={<Dashboard />} />
            <Route path="/leaderboard" element={<Leaderboard />} />
            <Route path="/players/:id" element={<PlayerProfile />} />

            <Route path="/tournaments" element={<Tournaments />} />
            <Route path="/tournaments/:id" element={<TournamentStandings />} />

            <Route path="/admin/import" element={<ImportTournament />} />
          </Routes>
        </Content>
      </BrowserRouter>
    </ConfigProvider>
  );
}

export default App;
