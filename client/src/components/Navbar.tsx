import { Link } from 'react-router-dom';

export default function Navbar() {
  return (
    <div className="navbar">
      <h2>Beyblade X Tracker</h2>

      <div className="links">
        <Link to="/">Leaderboard</Link>
        <Link to="/tournaments">Tournaments</Link>
      </div>
    </div>
  );
}
